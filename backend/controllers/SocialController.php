<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Repositories\AccountRepository;
use App\Repositories\CatalogRepository;
use App\Repositories\SocialRepository;
use App\Services\ModelManagement;
use PDO;

// API ถูกใจ บันทึก ติดตาม และคอมเมนต์ (เรียกจาก api/social.php)
/** Likes, saved models, follows and comments. */
final class SocialController extends Controller
{
    private const MAX_COMMENT_LENGTH = 1000;

    protected const ACTIONS = [
        'like' => 'like',
        'save' => 'save',
        'follow' => 'follow',
        'comments' => 'comments',
        'comment_add' => 'addComment',
        'comment_delete' => 'deleteComment',
    ];

    private readonly CatalogRepository $catalog;
    private readonly SocialRepository $social;
    private readonly AccountRepository $accounts;

    public function __construct(PDO $pdo)
    {
        parent::__construct($pdo);
        $this->catalog = new CatalogRepository($pdo);
        $this->social = new SocialRepository($pdo);
        $this->accounts = new AccountRepository($pdo);
    }

    // API กด/ยกเลิกถูกใจ แล้วส่งจำนวนไลก์ล่าสุดกลับไป
    protected function like(Request $request): Response
    {
        $this->guard($request);
        $modelId = $this->visibleModelId($request);
        $liked = $this->social->toggleLike($request->session()->userId(), $modelId);

        return $this->ok(['liked' => $liked, 'count' => $this->social->likeCount($modelId)]);
    }

    // API บันทึก/ยกเลิกบันทึกโมเดลเข้าคอลเลกชัน
    protected function save(Request $request): Response
    {
        $this->guard($request);

        return $this->ok(['saved' => $this->social->toggleCollection($request->session()->userId(), $this->visibleModelId($request))]);
    }

    // API ติดตาม/เลิกติดตามผู้ใช้ (ห้ามติดตามตัวเอง)
    protected function follow(Request $request): Response
    {
        $this->guard($request);
        $userId = $request->session()->userId();
        $targetId = $request->int('user_id');
        $this->abortUnless($targetId !== $userId, 422, 'self_follow', 'ไม่สามารถติดตามตัวเองได้');
        $this->abortUnless($this->accounts->find($targetId) !== null, 404, 'not_found', 'ไม่พบผู้ใช้');

        return $this->ok([
            'following' => $this->social->toggleFollow($userId, $targetId),
            'followers' => $this->social->followerCount($targetId),
        ]);
    }

    // API ดึงคอมเมนต์ทั้งหมดของโมเดล
    protected function comments(Request $request): Response
    {
        $modelId = $this->visibleModelId($request, 'model_id');

        return $this->ok([
            'comments' => $this->social->comments($modelId),
            'uid' => $request->session()->userId(),
            'admin' => $request->session()->isAdmin(),
        ]);
    }

    // API เพิ่มคอมเมนต์ (ไม่ว่าง และไม่เกิน 1000 ตัวอักษร)
    protected function addComment(Request $request): Response
    {
        $this->guard($request);
        $modelId = $this->visibleModelId($request, 'model_id');
        $body = $request->string('body');
        $this->abortUnless($body !== '', 422, 'empty_comment', 'กรุณาพิมพ์ความคิดเห็น');
        $this->abortUnless(mb_strlen($body) <= self::MAX_COMMENT_LENGTH, 422, 'too_long', 'ความคิดเห็นต้องไม่เกิน ' . self::MAX_COMMENT_LENGTH . ' ตัวอักษร');

        return $this->ok(['comment' => $this->social->addComment($modelId, $request->session()->userId(), $body)], 201);
    }

    // API ลบคอมเมนต์ (เจ้าของคอมเมนต์หรือแอดมิน)
    protected function deleteComment(Request $request): Response
    {
        $this->guard($request);
        $session = $request->session();
        $removed = $this->social->deleteComment($request->int('id'), $session->userId(), $session->isAdmin());
        $this->abortUnless($removed, 404, 'not_found', 'ไม่พบความคิดเห็น หรือไม่มีสิทธิ์ลบ');

        return $this->ok();
    }

    // คืน id โมเดลที่ผู้ใช้มีสิทธิ์ดู ถ้าไม่มีสิทธิ์ตอบ 404
    /** @return int id of the requested model, which the visitor must be allowed to see */
    private function visibleModelId(Request $request, string $key = 'id'): int
    {
        $id = $request->int($key);
        $model = $id > 0 ? $this->catalog->find($id) : null;
        $session = $request->session();
        $this->abortUnless(ModelManagement::canView($model, $session->userId(), $session->isAdmin()), 404, 'not_found', self::MODEL_NOT_FOUND);

        return $id;
    }
}
