<?php

declare(strict_types=1);

use yii\helpers\Html;
use yii\inertia\Page;
use yii\web\View;

/**
 * @var View $this
 * @var string $id
 * @var Page $page
 * @var string $pageJson
 * @var string $title
 */
?>
<div id="<?= Html::encode($id) ?>">
    <title><?= Html::encode($title) ?></title>
    <script type="application/json"><?= $pageJson ?></script>
</div>
