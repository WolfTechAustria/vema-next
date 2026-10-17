<?php

namespace App\Http\Controllers;

use App\Models\Post;
use Illuminate\Contracts\View\View;

/**
 * Vorschau eines Beitrags im Layout der Homepage — auch für Entwürfe und
 * noch nicht erreichte Veröffentlichungstermine.
 */
class HomepagePreviewController extends Controller
{
    public function post(Post $post, HomepageController $homepage): View
    {
        $post->load('images');

        return $homepage->renderPost($post, isPreview: true);
    }
}
