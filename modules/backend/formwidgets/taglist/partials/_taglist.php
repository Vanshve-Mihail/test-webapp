<?php
$selectedValues = is_array($selectedValues) ? $selectedValues : [];
$availableOptions = $useKey ? $fieldOptions : array_unique(array_merge($selectedValues, $fieldOptions));
$displayOnlyOptions = [];

foreach ($availableOptions as $key => $option) {
    if (!strlen($option)) {
        continue;
    }
    if (($useKey && in_array($key, $selectedValues)) || (!$useKey && in_array($option, $selectedValues))) {
        $displayOnlyOptions[] = $option;
    }
}
?>
<!-- Tag List -->
<?php if ($this->previewMode || $field->readOnly || $field->disabled): ?>
    <ul class="form-control taglist--preview" <?= $field->readOnly || $field->disabled ? 'disabled="disabled"' : ''; ?>>
        <?php foreach ($displayOnlyOptions as $option): ?>
            <li class="taglist__item"><?= e(trans($option)) ?></li>
        <?php endforeach ?>
    </ul>
    <?php if (is_array($field->value)): ?>
        <?php foreach ($displayOnlyOptions as $option): ?>
        <input
            type="hidden"
            name="<?= $field->getName() ?>[]"
            value="<?= $option ?>">
        <?php endforeach ?>
    <?php else: ?>
        <input
            type="hidden"
            name="<?= $field->getName() ?>[]"
            value="<?= $field->value ?>">
    <?php endif ?>
<?php else: ?>
    <input type="hidden" name="<?= $field->getName() ?>[]">
    <select
        id="<?= $field->getId() ?>"
        name="<?= $field->getName() ?>[]"
        class="form-control custom-select <?= !count($fieldOptions) ? 'select-no-dropdown' : '' ?> select-hide-selected"
        <?php if (!empty($customSeparators)): ?>
            data-token-separators="<?= $customSeparators ?>"
        <?php endif ?>
        <?php if (!empty($placeholder)): ?>
            data-placeholder="<?= e(trans($placeholder)) ?>"
        <?php endif ?>
        multiple
        <?= $field->getAttributes() ?>>
        <?php foreach ($availableOptions as $key => $option): ?>
            <?php if (!strlen($option)) {
                continue;
            } ?>
            <?php if ($useKey): ?>
                <option value="<?= e($key) ?>" <?= in_array($key, $selectedValues) ? 'selected="selected"' : '' ?>><?= e(trans($option)) ?></option>
            <?php else: ?>
                <option value="<?= e($option) ?>" <?= in_array($option, $selectedValues) ? 'selected="selected"' : '' ?>><?= e(trans($option)) ?></option>
            <?php endif ?>
        <?php endforeach ?>
    </select>
<?php endif ?>
