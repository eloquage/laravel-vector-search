<?php

use Eloquage\LaravelVectorSearch\Concerns\SearchableVector;
use Eloquage\LaravelVectorSearch\Jobs\ProcessVectorDocument;
use Eloquage\LaravelVectorSearch\Models\VectorDocument;
use Eloquage\LaravelVectorSearch\Models\VectorIndex;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    Schema::create('articles', function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->string('title');
        $table->text('content');
        $table->timestamps();
    });
});

afterEach(function (): void {
    Schema::dropIfExists('articles');
});

it('syncs vector document when source model is saved', function () {
    Queue::fake([ProcessVectorDocument::class]);

    VectorIndex::factory()->create([
        'name' => 'docs',
    ]);

    config()->set('vector-search.scout.default_index', 'docs');

    $article = SearchableArticle::query()->create([
        'title' => 'Deployment guide',
        'content' => 'Run migrations and queue workers.',
    ]);

    expect(VectorDocument::query()->count())->toBe(1);

    Queue::assertPushed(ProcessVectorDocument::class, 1);

    $article->update(['content' => 'Updated deployment content.']);

    expect(VectorDocument::query()->first()->content)->toBe('Updated deployment content.');
});

class SearchableArticle extends Model
{
    use SearchableVector;

    protected $table = 'articles';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['id', 'title', 'content'];

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if ($model->getKey() === null) {
                $model->setAttribute($model->getKeyName(), (string) str()->uuid());
            }
        });
    }

    public function vectorSearchContent(): string
    {
        return (string) $this->content;
    }

    public function vectorSearchTitle(): string
    {
        return (string) $this->title;
    }
}
