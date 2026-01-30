<?php
declare(strict_types=1);

namespace App\Controller\Trait;

use Cake\Datasource\EntityInterface;
use Cake\Event\EventInterface;

/**
 * CRUD Error Serialization Trait
 *
 * Provides consistent error serialization for FriendsOfCake/Crud plugin actions.
 * Extracts entity validation errors and formats them for JSON API responses.
 */
trait CrudErrorSerializationTrait
{
    protected function configureCrudErrorSerialization(): void
    {
        $this->Crud->on('afterSave', function (EventInterface $event): void {
            $subject = $event->getSubject();

            // @phpstan-ignore-next-line
            if (!$subject->success && $subject->entity->hasErrors()) {
                $errors = $this->extractEntityErrors($subject->entity);
                $this->set('errors', $errors);
            }
        });

        $this->Crud->on('afterDelete', function (EventInterface $event): void {
            $subject = $event->getSubject();

            // @phpstan-ignore-next-line
            if (!$subject->success && $subject->entity->hasErrors()) {
                $errors = $this->extractEntityErrors($subject->entity);
                $this->set('errors', $errors);

                $firstError = $this->getFirstErrorMessage($errors);
                if ($firstError) {
                    $this->set('message', $firstError);
                }
            }
        });
    }

    private function extractEntityErrors(EntityInterface $entity): array
    {
        $errors = [];

        foreach ($entity->getErrors() as $field => $fieldErrors) {
            if (is_array($fieldErrors)) {
                $errors[$field] = $this->flattenError($fieldErrors);
            } else {
                $errors[$field] = $fieldErrors;
            }
        }

        return $errors;
    }

    private function flattenError(array $errorArray): string
    {
        foreach ($errorArray as $value) {
            if (is_string($value)) {
                return $value;
            }
            if (is_array($value)) {
                return $this->flattenError($value);
            }
        }

        return 'Validation error';
    }

    private function getFirstErrorMessage(array $errors): ?string
    {
        return reset($errors) ?: null;
    }
}
