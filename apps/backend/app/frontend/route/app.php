<?php
use think\facade\Route;

// Theme engine catch-all — 必须最后注册，确保 API 路由优先
// 这些路由会在所有应用路由之后执行
// Multi-app dispatch maps the public `/media` prefix to this frontend app and
// removes the prefix before matching application routes.
Route::get(":hash", "Media/get")->pattern(["hash" => "[a-fA-F0-9]{16,64}"]);
Route::head(":hash", "Media/get")->pattern(["hash" => "[a-fA-F0-9]{16,64}"]);
Route::get("/", "frontend.Theme/index");
Route::get("/:path", "frontend.Theme/index")->pattern(["path" => "[\w\-/]+"]);
Route::rule("", "frontend.Theme/index");
