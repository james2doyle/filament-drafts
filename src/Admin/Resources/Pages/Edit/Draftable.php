<?php

namespace Guava\FilamentDrafts\Admin\Resources\Pages\Edit;

use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;
use Guava\FilamentDrafts\Admin\Actions\SaveDraftAction;
use Guava\FilamentDrafts\Admin\Actions\UnpublishAction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 *
 * @method Model getRecord()
 * @method string getResource()
 */
trait Draftable
{
    public bool $shouldSaveAsDraft = false;

    public function renderingDraftable(): void
    {
        Filament::registerRenderHook(
            'panels::content.end',
            function () {
                return view('filament-drafts::filament.revisions-paginator', [
                    'resource' => $this->getResource(),
                    'record' => $this->getRecord(),
                ]);
            }
        );
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        if ($record->isPublished() && $this->shouldSaveAsDraft) {
            $record->updateAsDraft($data);
        } elseif ($record->isPublished() && ! $this->shouldSaveAsDraft) {
            $record->update($data);
        } elseif (! $record->is_current && $this->shouldSaveAsDraft) {
            $record->updateAsDraft($data);
        } else {
            // Unpublish all other revisions
            if (! $this->shouldSaveAsDraft) {
                /** @var HasMany $revisions */
                $record::withoutTimestamps(fn () => $record->revisions()
                    ->where('is_published', true)
                    ->update(['is_published' => false]));
            }

            $record->update([
                ...$data,
                'is_published' => ! $this->shouldSaveAsDraft,
            ]);
        }

        $this->dispatch('updateRevisions', $record->id);

        return $record;
    }

    protected function getSaveFormAction(): Action
    {
        return parent::getSaveFormAction()
            ->color('success')
            ->label(
                fn (EditRecord $livewire) => $livewire->getRecord()->isPublished()
                ? __('filament-panels::resources/pages/edit-record.form.actions.save.label')
                : __('filament-drafts::actions.publish')
            );
    }

    protected function getFormActions(): array
    {
        return [
            ...array_slice(parent::getFormActions(), 0, 1),
            SaveDraftAction::make(),
            UnpublishAction::make(),
            ...array_slice(parent::getFormActions(), 1),
        ];
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return $this->shouldSaveAsDraft
            ? __('filament-drafts::notifications.draft_saved')
            : __('filament-panels::notifications.published');
    }

    protected function getSavedNotification(): ?Notification
    {
        $notification = parent::getSavedNotification();

        $this->shouldSaveAsDraft = false;

        return $notification;
    }
}
