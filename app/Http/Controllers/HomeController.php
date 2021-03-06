<?php

namespace App\Http\Controllers;


use App\Post;
use App\Services\BlogFeedUpdater;
use App\Tag;
use Illuminate\Support\Collection;

class HomeController extends Controller
{


    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {


        $managedTags = Tag::getAllPrimaryTags();

        $monthlyDay = 300;
        $monthlyBestByTag=[];
        $monthlyBestByTag['All'] = Post::getLastBestPosts($monthlyDay,5,Tag::getAllManagedTags());
        foreach($managedTags as $tag)
        {
            $monthlyBestByTag[$tag]= Post::getLastBestPosts($monthlyDay, 5,  Tag::MANAGED_TAGS[$tag]);
        }


        $latestPosts = Post::getLatestPosts(4);


        return view('pages.home.index', compact( 'monthlyBestByTag', 'latestPosts'));
    }
}
