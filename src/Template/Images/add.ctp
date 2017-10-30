<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Image $image
 */
?>
<nav class="large-3 medium-4 columns" id="actions-sidebar">
    <ul class="side-nav">
        <li class="heading"><?= __('Actions') ?></li>
        <li><?= $this->Html->link(__('List Images'), ['action' => 'index']) ?></li>
    </ul>
</nav>
<div class="images form large-9 medium-8 columns content">
    <?= $this->Form->create($image, ['type' => 'file']) ?>
    <fieldset>
        <legend><?= __('Add Image') ?></legend>
        <?php
            echo $this->Form->control('filename', ['type' => 'file']);
            echo $this->Form->control('top', ['type' => 'number','label' => 'Top']);
            echo $this->Form->control('left', ['type' => 'number','label' => 'Left']);
            echo $this->Form->control('imgw', ['type' => 'number','label' => 'Width']);
            echo $this->Form->control('imgh', ['type' => 'number','label' => 'Height']);
        ?>
    </fieldset>
    <?= $this->Form->button(__('Submit')) ?>
    <?= $this->Form->end() ?>
</div>
