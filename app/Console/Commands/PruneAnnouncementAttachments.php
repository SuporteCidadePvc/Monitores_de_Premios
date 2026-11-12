<?php

namespace App\Console\Commands;

use App\Domain\Admin\AdminManagementRepository;
use App\Domain\Admin\Exceptions\AdminDataUnavailable;
use App\Infrastructure\Announcements\AnnouncementAttachmentStore;
use Illuminate\Console\Command;

final class PruneAnnouncementAttachments extends Command
{
    protected $signature = 'monitores:announcements:prune-orphans {--delete : Apaga os ficheiros órfãos em vez de apenas os listar}';

    protected $description = 'Lista (ou apaga com --delete) ficheiros de anexos de comunicados que já não estão ligados a nenhum comunicado.';

    public function handle(AdminManagementRepository $management, AnnouncementAttachmentStore $attachments): int
    {
        try {
            $linked = array_flip($management->announcementAttachmentNames());
        } catch (AdminDataUnavailable) {
            $this->error('Não foi possível consultar os anexos na base de dados. Nenhum ficheiro foi apagado.');

            return self::FAILURE;
        }

        $minimumAgeSeconds = max(0, (int) config('announcements.attachments.orphan_min_age_minutes')) * 60;
        $cutoff = time() - $minimumAgeSeconds;
        $orphans = array_values(array_filter(
            $attachments->files(),
            fn (array $file): bool => ! isset($linked[$file['name']]) && $file['last_modified'] <= $cutoff,
        ));

        if ($orphans === []) {
            $this->info('Não existem ficheiros órfãos.');

            return self::SUCCESS;
        }

        foreach ($orphans as $file) {
            $this->line(sprintf('%s  %s', date('Y-m-d H:i', $file['last_modified']), $file['name']));
        }

        if (! $this->option('delete')) {
            $this->warn(sprintf('%d ficheiro(s) órfão(s). Execute com --delete para os apagar.', count($orphans)));

            return self::SUCCESS;
        }

        foreach ($orphans as $file) {
            $attachments->delete($file['name']);
        }

        $this->info(sprintf('%d ficheiro(s) órfão(s) apagado(s).', count($orphans)));

        return self::SUCCESS;
    }
}
