<?php

namespace App;

use App\Services\StripPosts;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Messages\SlackAttachment;
use Illuminate\Support\Facades\DB;

/**
 * App\Post
 * @property int $id
 * @property string $title
 * @property string $link
 * @property string $description
 * @property int $blog_id
 * @property \Illuminate\Support\Carbon $published_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Blog $blog
 * @property-read \App\Preview $preview
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Tag[] $tags
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Post newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Post newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Post query()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Post whereBlogId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Post whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Post whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Post whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Post whereLink($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Post wherePublishedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Post whereTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Post whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class Post extends Model
{
    protected $dates=['published_at'];
    protected $fillable = [
        'title',
        'link',
        'description',
        'published_at',
        'blog_id',
    ];


    public function getPublishedAtAttribute($date)
    {
        return Carbon::createFromFormat('Y-m-d H:i:s', $date)->format('y-m-d');
    }

    public function blog()
    {
        return $this->belongsTo(Blog::class);
    }

    public function tags()
    {
        return $this->belongsToMany(Tag::class)->withTimestamps();
    }

    public function preview()
    {
        return $this->hasOne(Preview::class)->withDefault(['image_url' => url('/img/adult-article-assortment-1496183.jpg')]);
    }

    public static function getLatestPosts(int $count, array $tagNames=[])
    {

        $posts = self::with('blog','preview','tags');


        if(count($tagNames))
            $posts->whereHas('tags',function(Builder $builder) use ($tagNames){
                $builder->whereIn('tags.name', $tagNames);
            });

        return $posts->limit($count)->get();

    }


    public static function getLastBestPosts(int $lastDays = 30, int $limit = 20, array $tagNames=[])
    {
        $tagCondition = '';
        if (count($tagNames)) {
            $tagNamesString = join("','", $tagNames);
            $tagCondition = " AND posts.id IN (select post_id from post_tag where tag_id in (select id from tags where name in ('$tagNamesString'))) ";
        }

        $sql = <<<SQL
SELECT count(posts.id) AS vcount, posts.*
FROM posts
LEFT JOIN viewcount
ON posts.id = viewcount.post_id
WHERE posts.published_at >= DATE(NOW()) - INTERVAL ? DAY
$tagCondition
GROUP BY posts.id
ORDER BY vcount DESC
LIMIT ?
SQL;
        $result = DB::select($sql, [$lastDays, $limit]);
        return Post::hydrate($result)->load('blog','preview','tags');
    }

    /**
     * @param int $rank
     * @return SlackAttachment
     */
    public function convertAttachment(int $rank): SlackAttachment
    {
        $url = route('posts.show', [$this->id]);
        $title = "$rank. {$this->title}";

        $attachment = new SlackAttachment();
        $attachment->authorName = $this->blog->title;
        $attachment->authorLink = $this->blog->site_url;
        $attachment->authorIcon = $this->blog->image_url;
        $attachment->title = $title;
        $attachment->url = $url;
        $attachment->content = StripPosts::slack($this->description);
        $attachment->timestamp = strtotime($this->getAttributes()['published_at']);

        foreach ($this->tags as $tag) {
            $attachment->action($tag->name, route('posts.search', [$tag->name]));
        }
        return $attachment;
    }

}
