<?php

use App\Enums\Tracker\TaskDefinitionStatus;

describe('label', function () {
    it('uses the translation from lang/{locale}/enums.php when one exists', function () {
        app('translator')->addLines([
            'enums.task_definition_status.active' => 'Currently active',
        ], 'en');

        expect(TaskDefinitionStatus::Active->label())->toBe('Currently active');
    });

    it('falls back to the value in title case when there is no translation', function () {
        expect(TaskDefinitionStatus::Paused->label())->toBe('Paused');
    });

    it('uses the translated label in select items', function () {
        app('translator')->addLines([
            'enums.task_definition_status.archived' => 'Archived tasks',
        ], 'en');

        expect(TaskDefinitionStatus::Archived->toSelectItem())->toBe([
            'value' => 'archived',
            'label' => 'Archived tasks',
        ]);
    });
});

describe('labelTranslationKey', function () {
    it('builds the key from the snake case enum name and the value', function () {
        expect(TaskDefinitionStatus::labelTranslationKey('active'))
            ->toBe('enums.task_definition_status.active');
    });
});
