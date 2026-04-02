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
 */
$this->beginPage();
?>
<!DOCTYPE html>
<html lang="<?= Html::encode(Yii::$app->language) ?>">
    <head>
        <meta charset="<?= Html::encode(Yii::$app->charset) ?>">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title data-inertia><?= Html::encode(Yii::$app->name) ?></title>
        <?php $this->head(); ?>
    </head>
    <body>
    <?php $this->beginBody(); ?>
        <div id="<?= Html::encode($id) ?>">
            <script type="application/json"><?= $pageJson ?></script>
        </div>
    <?php $this->endBody(); ?>
    </body>
</html>
<?php $this->endPage();
