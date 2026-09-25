<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\HttpException;
use App\Http\Request;
use App\Http\Response;
use App\Http\View;
use App\Repositories\CatalogRepository;
use App\Repositories\CommerceQueries;
use App\Repositories\SocialRepository;
use App\Services\ModelConverter;
use App\Services\ModelFiles;
use App\Services\ModelManagement;
use App\Services\ModelRemover;
use App\Services\UploadValidator;
use App\Support\Config;
use PDO;

/** Model detail page, embed view, downloads and the upload / edit / delete API. */
final class ModelController extends Controller
{
    protected const ACTIONS = [
        'get' => 'get',
        'create' => 'create',
        'update' => 'update',
        'delete' => 'delete',
        'visibility' => 'visibility',
    ];

    private const DOWNLOAD_COLUMNS = ['gltf' => 'file_gltf', 'glb' => 'file_glb', 'usdz' => 'file_usdz', 'obj' => 'file_obj'];
    private const MODEL_SAVE_FAILED = 'บันทึกไฟล์โมเดลไม่สำเร็จ';
    private const THUMB_SAVE_FAILED = 'บันทึกรูปภาพไม่สำเร็จ';
    private const MODEL_FILE_COLUMNS = ['filename', 'file_gltf', 'file_glb', 'file_usdz', 'file_obj'];

    private readonly CatalogRepository $catalog;
    private readonly SocialRepository $social;
    private readonly CommerceQueries $commerce;
    private readonly ModelFiles $files;
    private readonly ModelConverter $converter;
    private readonly ModelRemover $remover;

    public function __construct(PDO $pdo, ?ModelFiles $files = null, ?ModelConverter $converter = null)
    {
        parent::__construct($pdo);
        $this->catalog = new CatalogRepository($pdo);
        $this->social = new SocialRepository($pdo);
        $this->commerce = new CommerceQueries($pdo);
        $this->files = $files ?? new ModelFiles(Config::uploadDir());
        $this->converter = $converter ?? new ModelConverter($this->files, Config::blenderPath(), Config::root() . '/backend/scripts/convert.py');
        $this->remover = new ModelRemover($this->catalog, $this->files);
    }

    // ── Pages ────────────────────────────────────────────────────────

    public function show(Request $request): Response
    {
        $session = $request->session();
        $userId = $session->userId();
        $model = $this->visibleModel($request->int('id'), $request);
        if ($model === null) {
            return $this->notFound($request, 'ไม่พบโมเดลนี้ หรือเจ้าของตั้งค่าไม่แสดงสาธารณะ');
        }
        $id = (int) $model['id'];
        $isOwner = ModelManagement::isOwner($userId, (int) $model['user_id']);

        // Count one view per visitor session, never for the owner.
        $viewed = (array) $session->get('viewed', []);
        if (!$isOwner && !in_array($id, $viewed, true)) {
            $this->catalog->incrementViews($id);
            $model['view_count'] = (int) $model['view_count'] + 1;
            $session->set('viewed', [...$viewed, $id]);
        }

        $viewer = ModelManagement::viewerFile($model, $this->files);
        $purchase = $userId > 0 ? $this->commerce->purchaseState($userId, $id) : null;
        $data = [
            'title' => $model['title'] . ' – 3D Gallery',
            'nav' => 'model',
            'scripts' => ['viewer.js', 'model.js'],
            'model' => $model,
            'tags' => $this->catalog->tagNames($id),
            'viewerFile' => $viewer['file'],
            'viewerExt' => $viewer['ext'],
            'size' => $this->files->size((string) $model['filename']),
            'isOwner' => $isOwner,
            'liked' => $userId > 0 && $this->social->isLiked($userId, $id),
            'saved' => $userId > 0 && $this->social->isSaved($userId, $id),
            'following' => $userId > 0 && $this->social->isFollowing($userId, (int) $model['user_id']),
            'followers' => $this->social->followerCount((int) $model['user_id']),
            'purchase' => $purchase,
            'canDownload' => !$this->requiresPayment($model, $request) || $purchase === 'approved',
            'formats' => ModelManagement::downloadFormats($model, $this->files),
            'license' => ModelManagement::licenseInfo((string) $model['license']),
            'suggested' => $this->catalog->suggested($id, 8),
        ];

        return $request->query('embed') === '1'
            ? Response::html(View::render('pages/embed', $data + ['csrf' => $session->csrf()]))
            : View::page($request, 'pages/model', $data);
    }

    public function download(Request $request): Response
    {
        $model = $this->visibleModel($request->int('id'), $request);
        if ($model === null) {
            return $this->notFound($request, 'ไม่พบโมเดลหรือคุณไม่มีสิทธิ์ดาวน์โหลด');
        }
        $denied = $this->downloadGate($request, $model);
        $column = self::DOWNLOAD_COLUMNS[$request->string('format')] ?? 'filename';
        $name = (string) ($model[$column] ?: $model['filename']);

        return $denied
            ?? ($this->files->exists($name)
                ? Response::download($this->files->path($name), ModelManagement::downloadName((string) $model['title'], $name))
                : $this->notFound($request, 'ไม่พบไฟล์บนเซิร์ฟเวอร์'));
    }

    // ── JSON API ─────────────────────────────────────────────────────

    /** Model fields for the edit dialog (owner only). */
    protected function get(Request $request): Response
    {
        $this->guard($request, post: false);
        $model = $this->ownedModel($request);

        return $this->ok(['model' => [
            'id' => (int) $model['id'],
            'title' => $model['title'],
            'description' => (string) $model['description'],
            'license' => $model['license'],
            'price' => (float) $model['price'],
            'is_public' => (int) $model['is_public'],
            'thumb' => $model['thumb'],
            'tags' => implode(', ', $this->catalog->tagNames((int) $model['id'])),
        ]]);
    }

    protected function create(Request $request): Response
    {
        $this->abortUnless(!$request->bodyWasDiscarded(), 413, 'too_large', 'ขนาดไฟล์รวมทั้งหมดใหญ่เกินกว่าที่เซิร์ฟเวอร์รองรับ (เกิน ' . ini_get('post_max_size') . ')');
        $this->guard($request);
        $title = $this->requiredTitle($request);
        $modelFile = $request->file('model');
        $thumbFile = $request->file('thumb');
        $this->validateUploads($modelFile, $thumbFile, true);

        $filename = $this->storeUpload((array) $modelFile, '', self::MODEL_SAVE_FAILED);
        $thumbName = UploadValidator::hasFile($thumbFile) ? $this->storeThumbOrCleanup((array) $thumbFile, [$filename]) : '';

        $id = $this->catalog->create([
            'user_id' => $request->session()->userId(),
            'title' => $title,
            'filename' => $filename,
            'thumb' => $thumbName,
            'description' => $request->string('description'),
            'license' => ModelManagement::normalizeLicense($request->string('license')),
            'price' => ModelManagement::normalizePrice($request->string('price', '0')),
        ]);
        $this->catalog->syncTags($id, ModelManagement::normalizeTags($request->string('tags')));
        $this->applyConversions($id, $filename);

        return $this->ok(['id' => $id, 'url' => 'model.php?id=' . $id], 201);
    }

    protected function update(Request $request): Response
    {
        $this->guard($request);
        $model = $this->ownedModel($request);
        $title = $this->requiredTitle($request);
        $modelFile = $request->file('model');
        $thumbFile = $request->file('thumb');
        $this->validateUploads($modelFile, $thumbFile, UploadValidator::hasFile($modelFile));

        // Store everything first so a failure leaves the current files untouched.
        $newModel = UploadValidator::hasFile($modelFile) ? $this->storeUpload((array) $modelFile, '', self::MODEL_SAVE_FAILED) : null;
        $newThumb = UploadValidator::hasFile($thumbFile) ? $this->storeThumbOrCleanup((array) $thumbFile, array_filter([$newModel])) : null;

        $this->catalog->update((int) $model['id'], [
            'title' => $title,
            'description' => $request->string('description'),
            'filename' => $newModel ?? $model['filename'],
            'thumb' => $newThumb ?? $model['thumb'],
            'license' => ModelManagement::normalizeLicense($request->string('license')),
            'price' => ModelManagement::normalizePrice($request->string('price', '0')),
        ]);
        $this->catalog->syncTags((int) $model['id'], ModelManagement::normalizeTags($request->string('tags')));
        $this->replaceFiles($model, $newModel, $newThumb);

        return $this->ok(['title' => $title]);
    }

    protected function delete(Request $request): Response
    {
        $this->guard($request);
        $this->abortUnless(
            $this->remover->remove($this->ownedModel($request)),
            409,
            'has_sales',
            'โมเดลนี้มีประวัติการขายจึงลบไม่ได้ — ให้ซ่อนโมเดลแทน'
        );

        return $this->ok();
    }

    protected function visibility(Request $request): Response
    {
        $this->guard($request);
        $model = $this->ownedModel($request);
        $public = $request->int('is_public', 1) === 1;
        $this->catalog->setVisibility((int) $model['id'], $public);

        return $this->ok(['is_public' => $public ? 1 : 0]);
    }

    // ── Helpers ──────────────────────────────────────────────────────

    /** @return array<string, mixed>|null the model when the current visitor may see it */
    private function visibleModel(int $id, Request $request): ?array
    {
        $model = $id > 0 ? $this->catalog->find($id) : null;
        $session = $request->session();

        return ModelManagement::canView($model, $session->userId(), $session->isAdmin()) ? $model : null;
    }

    /** @return array<string, mixed> the requested model, which must belong to the current user */
    private function ownedModel(Request $request): array
    {
        $model = $this->catalog->find($request->int('id'));
        $this->abortUnless($model !== null && ModelManagement::isOwner($request->session()->userId(), (int) $model['user_id']), 404, 'not_found', self::MODEL_NOT_FOUND);

        return (array) $model;
    }

    /** @param array<string, mixed> $model */
    private function requiresPayment(array $model, Request $request): bool
    {
        $session = $request->session();

        return (float) $model['price'] > 0
            && !$session->isAdmin()
            && !ModelManagement::isOwner($session->userId(), (int) $model['user_id']);
    }

    /**
     * @param array<string, mixed> $model
     * @return Response|null a "forbidden" page when the visitor has not paid, otherwise null
     */
    private function downloadGate(Request $request, array $model): ?Response
    {
        $userId = $request->session()->userId();
        $heading = 'ไม่สามารถดาวน์โหลดได้';
        $message = 'โมเดลนี้มีราคา กรุณาชำระเงินและรอให้ผู้ดูแลระบบอนุมัติก่อนดาวน์โหลด';
        if ($userId === 0) {
            $heading = 'คุณยังไม่ได้เข้าสู่ระบบ';
            $message = 'กรุณาเข้าสู่ระบบก่อนสั่งซื้อและดาวน์โหลดโมเดลนี้';
        }
        $allowed = !$this->requiresPayment($model, $request)
            || ($userId > 0 && $this->commerce->purchaseState($userId, (int) $model['id']) === 'approved');

        return $allowed ? null : View::page($request, 'pages/error', [
            'title' => $heading,
            'heading' => $heading,
            'message' => $message,
            'backUrl' => 'model.php?id=' . (int) $model['id'],
            'backLabel' => 'กลับไปที่หน้าโมเดล',
        ], 403);
    }

    private function requiredTitle(Request $request): string
    {
        $title = $request->string('title');
        $this->abortUnless($title !== '', 422, 'title_required', 'กรุณากรอกชื่อโมเดล');

        return $title;
    }

    /**
     * @param array<string, mixed>|null $modelFile
     * @param array<string, mixed>|null $thumbFile
     */
    private function validateUploads(?array $modelFile, ?array $thumbFile, bool $modelRequired): void
    {
        $error = ($modelRequired ? UploadValidator::validateModel($modelFile) : null) ?? UploadValidator::validateThumbnail($thumbFile);
        $this->abortUnless($error === null, 422, 'invalid_upload', (string) $error);
    }

    /** @param array<string, mixed> $upload */
    private function storeUpload(array $upload, string $suffix, string $failure): string
    {
        $name = $this->files->randomName(strtolower(pathinfo((string) $upload['name'], PATHINFO_EXTENSION)), $suffix);
        $this->abortUnless($this->files->store($upload, $name), 500, 'save_failed', $failure);

        return $name;
    }

    /**
     * @param array<string, mixed> $upload
     * @param list<string> $cleanup already-stored files to remove when the thumbnail cannot be saved
     */
    private function storeThumbOrCleanup(array $upload, array $cleanup): string
    {
        try {
            return $this->storeUpload($upload, '_thumb', self::THUMB_SAVE_FAILED);
        } catch (HttpException $e) {
            array_map($this->files->delete(...), $cleanup);
            throw $e;
        }
    }

    /**
     * Deletes the files a model no longer uses after an edit and refreshes its conversions.
     *
     * @param array<string, mixed> $old
     */
    private function replaceFiles(array $old, ?string $newModel, ?string $newThumb): void
    {
        if ($newModel !== null) {
            foreach (self::MODEL_FILE_COLUMNS as $column) {
                $this->files->delete((string) $old[$column]);
            }
            $this->applyConversions((int) $old['id'], $newModel);
        }
        if ($newThumb !== null) {
            $this->files->delete((string) $old['thumb']);
        }
    }

    private function applyConversions(int $modelId, string $filename): void
    {
        $formats = $this->converter->convert($filename);
        $this->catalog->setConversions($modelId, $formats['gltf'], $formats['glb'], $formats['usdz'], $formats['obj']);
    }
}
