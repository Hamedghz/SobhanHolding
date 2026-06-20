<?php
function sobhan_seed_result(array $counts, int $expected): array
{
    $inserted = array_sum(array_map('intval', $counts));
    return ['inserted' => $inserted, 'updated' => 0, 'skipped' => max(0, $expected - $inserted), 'errors' => 0, 'details' => $counts];
}
