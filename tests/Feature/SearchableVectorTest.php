<?php

use Eloquage\LaravelVectorSearch\Concerns\SearchableVector;
use Eloquage\LaravelVectorSearch\Enums\EmbeddingProvider;
use Eloquage\LaravelVectorSearch\Jobs\ProcessVectorDocument;
use Eloquage\LaravelVectorSearch\Models\VectorDocument;
use Eloquage\LaravelVectorSearch\Models\VectorIndex;
use Eloquage\LaravelVectorSearch\Services\VectorDocumentPurger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;

class SearchableVectorPost extends Model
{
    use SearchableVector;

    protected $table = 'searchable_vector_posts';

    protected $fillable = ['title', 'content', 'views'];
}

beforeEach(function () {
    Schema::create('searchable_vector_posts', function (Blueprint $table) {
        $table->id();
        $table->string('title');
        $table->text('content');
        $table->unsignedInteger('views')->default(0);
        $table->timestamps();
    });

    VectorIndex::factory()->create([
        'name' => 'default',
        'embedding_provider' => EmbeddingProvider::NULL_PROVIDER,
        'dimensions' => 8,
        'embedding_model' => 'null',
    ]);
});

it('does not dispatch vector jobs when unrelated fields change', function () {
    Queue::fake();

    $post = SearchableVectorPost::query()->create([
        'title' => 'Hello',
        'content' => 'World',
    ]);

    Queue::assertPushed(ProcessVectorDocument::class, 1);

    $post->update(['views' => 1]);

    Queue::assertPushed(ProcessVectorDocument::class, 1);
});

it('purges vector documents when the source model is deleted', function () {
    Queue::fake();

    $post = SearchableVectorPost::query()->create([
        'title' => 'Hello',
        'content' => 'World',
    ]);

    expect(VectorDocument::query()->count())->toBe(1);

    $purger = Mockery::mock(VectorDocumentPurger::class);
    $purger->shouldReceive('purge')->once();
    app()->instance(VectorDocumentPurger::class, $purger);

    $post->delete();
});
