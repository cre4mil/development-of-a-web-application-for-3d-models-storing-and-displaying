<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Http\View;
use App\Repositories\CatalogRepository;
use App\Repositories\SocialRepository;
use App\Services\ModelFiles;
use App\Services\ModelManagement;
use App\Support\Config;
use PDO;

/** Public gallery: search, filters, sorting and pagination. */
final class HomeController extends Controller
{
    public const PER_PAGE = 12;

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
        $filters = [
            'search' => $request->string('search'),
            'tag' => $request->string('tag'),
            'license' => $request->string('license'),
            'date' => $request->string('date'),
            'sort' => $request->string('sort'),
        ];
        $result = $this->catalog->search($filters, $request->int('page', 1), self::PER_PAGE);
        $userId = $request->session()->userId();

        return View::page($request, 'pages/home', [
            'title' => '3D Gallery — คลังโมเดล 3 มิติ',
            'nav' => 'home',
            'scripts' => ['viewer.js', 'gallery.js'],
            'filters' => $filters,
            'models' => ModelCards::prepare($result['rows'], $this->catalog, $this->social, $this->files, $userId),
            'total' => $result['total'],
            'page' => $result['page'],
            'pages' => $result['pages'],
            'allTags' => $this->catalog->allTags(),
            'licenses' => ModelManagement::licenseCodes(),
            'query' => array_filter($filters, static fn (string $value): bool => $value !== ''),
        ]);
    }
}
