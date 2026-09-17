<?php
/**
 * Pagination links — expects a \Cms\Support\Paginator.
 *
 * @var \Cms\Support\Paginator $paginator
 */
if (!isset($paginator) || !$paginator->hasPages()) {
    return;
}
?>
<div class="pagination">
    <p class="pagination__summary"><?= e($paginator->summary()) ?></p>
    <?= $paginator->links() ?>
</div>
