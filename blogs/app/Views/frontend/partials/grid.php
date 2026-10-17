<?php /** @var array $posts */ ?>
<div class="bf-grid">
  <?php foreach ($posts as $i => $p): ?>
    <?= $this->insert('frontend/partials/card', ['post' => $p, 'headingTag' => $headingTag ?? 'h2', 'large' => false, 'priority' => !empty($priorityFirst) && $i === 0]) ?>
  <?php endforeach; ?>
</div>
