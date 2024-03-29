<?php

namespace App\Http\Controllers;

use App\Article;
use App\Category;
use App\User;
use App\Tag;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Http\Controllers\Controller;
use App\Http\Helpers;

class ArticlesController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth', ['except'=>["show"]]);
    }
    
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $followers = auth()->user()->profile->followers()->pluck('user_id');
        $followings = auth()->user()->following->pluck('user_id');
        $articles = Article::whereIn("user_id", [auth()->user()->id])
                            ->orWhereIn("user_id", $followers)
                            ->orWhereIn("user_id", $followings)
                            ->with("user")
                            ->latest()
                            ->get();
        return view("articles.index", compact("articles"));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $categories = Category::all();
        return view("articles.create", compact("categories"));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $this->validate($request, [
            'title' => 'required',
            'category_id' => 'required',
            'content' => 'required',
            'feature' => 'nullable|image|max:5999',
            'tags' => 'nullable'
        ]);

        if ($request->hasFile('feature')) {
            $filenameWithExtension = $request->file("feature")->getClientOriginalName();
            $extension = $request->file("feature")->getClientOriginalExtension();
            $filenameWithoutExtension = pathinfo($filenameWithExtension, PATHINFO_FILENAME);
            $filenameToStore = $filenameWithoutExtension."_".time().".".$extension;

            $request->file("feature")->storeAs("public/features", $filenameToStore);
        }
        else
            $filenameToStore = "noimage.jpg";

        $article = new Article;
        $article->user_id = auth()->user()->id;
        $article->title = $request->input("title");
        $article->category_id = $request->input("category_id");
        $article->content = $request->input("content");
        $article->feature = $filenameToStore;
        $article->save();
        
        if($request->input("tags"))
            handleTags($article, $request->input("tags"), false);

        return redirect('/articles')->with("success", "Article published succesfully!");
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Article  $article
     * @return \Illuminate\Http\Response
     */
    public function show(Article $article)
    {
        $article = Article::find($article->id);
        $all_comments = $article->comments;
        $likes = ((auth()->user()) ? auth()->user()->profile->liking->contains($article) : false);
        $users = $article->likes()->pluck('user_id');
        $likers = User::whereIn("id", $users)->get();

        if(Auth()->user()){
            // Filters out the comments of people i blocked/blocked me (for authentificated users)
            $comments = array();
            foreach ($all_comments as $comment) {
                $blocked = false;
                $user = auth()->user();
                $commentPoster = User::find($comment->user_id);
                $user_blocked_commentPoster = ($user->blocking->contains($commentPoster->profile));
                $user_blocked_by_commentPoster = ($user->profile->blockers->contains($commentPoster));
                if(!$user_blocked_commentPoster && !$user_blocked_by_commentPoster)
                    array_push($comments, $comment);
            }
            $comments = collect($comments)->map(function ($comment) {
                return (object) $comment;
            });
            // Comment filtering ends.
        }
        else
            $comments = $all_comments;

        return view("articles.show", compact("article", "comments", "likes", "likers"));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Article  $article
     * @return \Illuminate\Http\Response
     */
    public function edit(Article $article)
    {
        $this->authorize('update', $article);

        $article = Article::find($article->id);
        $categories = Category::all();
        $tags = implode(',', $article->tags()->get()->pluck('name')->toArray());

        return view("articles.edit", compact("article", "categories", "tags"));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Article  $article
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Article $article)
    {
        $this->authorize('update', $article);
        
        $data = request()->validate([
            'title' => 'required',
            'content' => 'required',
            'feature' => 'nullable|image|max:5999'
        ]);
        
        handleTags($article, $request->input("tags"), true);

        if ($request->hasFile('feature')) {
            if($article->feature != "noimage.jpg")
                Storage::delete("public/features/".$article->feature);

            $filenameWithExtension = $request->file("feature")->getClientOriginalName();
            $extension = $request->file("feature")->getClientOriginalExtension();
            $filenameWithoutExtension = pathinfo($filenameWithExtension, PATHINFO_FILENAME);
            $filenameToStore = $filenameWithoutExtension."_".time().".".$extension;

            $request->file("feature")->storeAs("public/features", $filenameToStore);

            $article->update(array_merge(
                $data,
                ['feature' => $filenameToStore]
            ));
        }
        else{
            $article->update($data);
        }

        return redirect('/articles/'.$article->id)->with("success", "Article updated succesfully!");
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Article  $article
     * @return \Illuminate\Http\Response
     */
    public function destroy(Article $article)
    {
        $this->authorize('delete', $article);

        $article = Article::find($article->id);

        if($article->feature != "noimage.jpg")
            Storage::delete("public/features/".$article->feature);

        if(!empty($article->tags))
            $article->tags()->detach();
        
        $article->delete();

        return redirect('/articles')->with("success", "Article deleted succesfully!");
    }
}
