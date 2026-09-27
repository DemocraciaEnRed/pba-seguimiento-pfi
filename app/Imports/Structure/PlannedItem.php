<?php

namespace App\Imports\Structure;

use Illuminate\Database\Eloquent\Model;

final class PlannedItem
{
    /**
     * @var list<PlannedItem>
     */
    public array $children = [];

    /**
     * @param  array<string, array{0: string, 1: string}>  $changes  Column header => [before, after].
     * @param  array<string, mixed>  $input  Validated goal form input.
     */
    public function __construct(
        public Model $model,
        public int $line,
        public ImportAction $action,
        public array $changes = [],
        public array $input = [],
    ) {}
}
