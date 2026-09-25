<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Http\View;
use App\Repositories\CatalogRepository;
use App\Repositories\SocialRepository;
use App\Services\ModelFiles;
use App\Support\Config;
use PDO;

/** The signed-in user's own pages: profile (models, saved, stats) and liked models. */
final class ProfileController extends Controller
{
    private readonly CatalogRepository $catalog;
    private readonly SocialRepository $social;
    private readonly ModelFiles $files;

    public function __construct(PDO $pdo, ?ModelFiles $files = null)
    {
        parent::__construct($pdo);
        $this->catalog = new CatalogRepository($pdo);
        $this->social = new SocialRepository($pdo);
        $this->files = $files ?? new ModelFiles(Config::uploadDir());
    }

    public function index(Request $request): Response
    {
        if (($redirect = $this->pageGuard($request)) !== null) {
            return $redirect;
        }
        $userId = $request->session()->userId();
        $mine = ModelCards::prepare($this->catalog->ownedBy($userId), $this->catalog, $this->social, $this->files, $userId);
        $saved = ModelCards::prepare($this->catalog->savedBy($userId), $this->catalog, $this->social, $this->files, $userId);

        return View::page($request, 'pages/profile', [
            'title' => 'โปรไฟล์ – 3D Gallery',
            'nav' => 'profile',
            'scripts' => ['viewer.js', 'gallery.js'],
            'mine' => $mine,
            'saved' => $saved,
            'followers' => $this->social->followerCount($userId),
            'totals' => [
                'models' => count($mine),
                'views' => array_sum(array_column($mine, 'view_count')),
                'likes' => array_sum(array_column($mine, 'like_count')),
                'comments' => array_sum(array_column($mine, 'comment_count')),
            ],
        ]);
    }

    public function liked(Request $request): Response
    {
        if (($redirect = $this->pageGuard($request)) !== null) {
            return $redirect;
        }
        $userId = $request->session()->userId();

        return View::page($request, 'pages/liked', [
            'title' => 'โมเดลที่ชอบ – 3D Gallery',
            'nav' => 'liked',
            'scripts' => ['viewer.js', 'gallery.js'],
            'models' => ModelCards::prepare($this->catalog->likedBy($userId), $this->catalog, $this->social, $this->files, $userId),
        ]);
    }
}
