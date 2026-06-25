<?php

use Illuminate\Support\Facades\Schema;

it('runs vector migrations up and down on sqlite', function () {
    $alter = include dirname(__DIR__, 2).'/database/migrations/alter_vector_chunks_embedding_to_vector_column.php.stub';

    expect(Schema::hasTable('vector_chunks'))->toBeTrue();

    $alter->down();

    expect(Schema::hasColumn('vector_chunks', 'embedding'))->toBeTrue();

    $alter->up();
});
