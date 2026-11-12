<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Admin\AdminManagementRepository;
use App\Domain\Admin\AdminUser;
use App\Domain\Admin\Exceptions\AdminDataUnavailable;
use App\Domain\Admin\Exceptions\AdminOperationFailed;
use App\Domain\Announcements\AnnouncementFile;
use App\Domain\Announcements\Exceptions\AnnouncementAttachmentRejected;
use App\Infrastructure\Announcements\AnnouncementAttachmentStore;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class AdminManagementController
{
    public function index(AdminManagementRepository $management): View
    {
        try {
            $dashboard = $management->dashboard();
            $unavailable = false;
        } catch (AdminDataUnavailable) {
            $dashboard = ['rules' => [], 'announcements' => [], 'audit' => []];
            $unavailable = true;
        }

        return view('admin.dashboard', $dashboard + [
            'user' => $this->user(),
            'unavailable' => $unavailable,
        ]);
    }

    public function storePrizeRule(Request $request, AdminManagementRepository $management): RedirectResponse
    {
        $data = $this->validatedPrizeRule($request, true);

        try {
            $management->createPrizeRule(
                (string) $data['sector'],
                (int) $data['minimum_squares'],
                $this->toCents((string) $data['prize_pool']),
                $this->user(),
            );
        } catch (AdminDataUnavailable|AdminOperationFailed) {
            return back()->withErrors(['rules' => 'Não foi possível guardar o escalão. Nenhuma alteração foi confirmada.'])->withInput();
        }

        return back()->with('status', 'Novo escalão de prémio criado e registado na auditoria.');
    }

    public function updatePrizeRule(Request $request, int $id, AdminManagementRepository $management): RedirectResponse
    {
        $data = $this->validatedPrizeRule($request, false);

        try {
            $management->updatePrizeRule(
                $id,
                (int) $data['minimum_squares'],
                $this->toCents((string) $data['prize_pool']),
                $this->user(),
            );
        } catch (AdminDataUnavailable|AdminOperationFailed) {
            return back()->withErrors(['rules' => 'Não foi possível atualizar o escalão. Nenhuma alteração foi confirmada.'])->withInput();
        }

        return back()->with('status', 'Escalão de prémio atualizado e registado na auditoria.');
    }

    public function deactivatePrizeRule(Request $request, int $id, AdminManagementRepository $management): RedirectResponse
    {
        $request->validate(['confirm' => ['accepted']]);

        try {
            $management->deactivatePrizeRule($id, $this->user());
        } catch (AdminDataUnavailable|AdminOperationFailed) {
            return back()->withErrors(['rules' => 'Não foi possível desativar o escalão.'])->withInput();
        }

        return back()->with('status', 'Escalão desativado e registado na auditoria.');
    }

    public function deletePrizeRule(Request $request, int $id, AdminManagementRepository $management): RedirectResponse
    {
        $request->validate(['confirm_delete' => ['accepted']]);

        try {
            $management->deletePrizeRule($id, $this->user());
        } catch (AdminDataUnavailable|AdminOperationFailed) {
            return back()->withErrors(['rules' => 'Não foi possível remover o escalão. Escalões já usados em prémios mensais devem ser desativados para preservar o histórico.'])->withInput();
        }

        return back()->with('status', 'Escalão removido e registado na auditoria.');
    }

    public function storeAnnouncement(Request $request, AdminManagementRepository $management, AnnouncementAttachmentStore $attachments): RedirectResponse
    {
        $data = $this->validatedAnnouncement($request, false);

        try {
            $file = $this->storedAttachment($request, $attachments);
        } catch (AnnouncementAttachmentRejected $exception) {
            return back()->withErrors(['attachment' => $this->attachmentMessage($exception)])->withInput();
        }

        try {
            $management->createAnnouncement(
                (string) $data['scope'],
                trim((string) $data['title']),
                trim((string) ($data['body'] ?? '')),
                $this->localDateTime((string) $data['starts_at']),
                isset($data['ends_at']) && $data['ends_at'] !== null ? $this->localDateTime((string) $data['ends_at']) : null,
                $this->user(),
                $file,
            );
        } catch (AdminDataUnavailable|AdminOperationFailed) {
            if ($file !== null) {
                $attachments->delete($file->storedName);
            }

            return back()->withErrors(['announcements' => 'Não foi possível guardar o comunicado. Nenhuma alteração foi confirmada.'])->withInput();
        }

        return back()->with('status', 'Comunicado criado e registado na auditoria.');
    }

    public function deactivateAnnouncement(Request $request, int $id, AdminManagementRepository $management): RedirectResponse
    {
        $request->validate(['confirm' => ['accepted']]);

        try {
            $management->deactivateAnnouncement($id, $this->user());
        } catch (AdminDataUnavailable|AdminOperationFailed) {
            return back()->withErrors(['announcements' => 'Não foi possível desativar o comunicado.'])->withInput();
        }

        return back()->with('status', 'Comunicado desativado e registado na auditoria.');
    }

    public function updateAnnouncement(Request $request, int $id, AdminManagementRepository $management, AnnouncementAttachmentStore $attachments): RedirectResponse
    {
        $data = $this->validatedAnnouncement($request, true);

        try {
            $file = $this->storedAttachment($request, $attachments);
        } catch (AnnouncementAttachmentRejected $exception) {
            return back()->withErrors(['attachment' => $this->attachmentMessage($exception)])->withInput();
        }

        try {
            $unlinkedAttachment = $management->updateAnnouncement(
                $id,
                (string) $data['scope'],
                trim((string) $data['title']),
                trim((string) ($data['body'] ?? '')),
                $this->localDateTime((string) $data['starts_at']),
                isset($data['ends_at']) && $data['ends_at'] !== null ? $this->localDateTime((string) $data['ends_at']) : null,
                $this->user(),
                $file,
                $file === null && $request->boolean('remove_attachment'),
            );
        } catch (AdminDataUnavailable|AdminOperationFailed) {
            if ($file !== null) {
                $attachments->delete($file->storedName);
            }

            return back()->withErrors(['announcements' => 'Não foi possível atualizar o comunicado. Nenhuma alteração foi confirmada.'])->withInput();
        }

        if ($unlinkedAttachment !== null) {
            $attachments->delete($unlinkedAttachment);
        }

        return back()->with('status', 'Comunicado atualizado e registado na auditoria.');
    }

    public function deleteAnnouncement(Request $request, int $id, AdminManagementRepository $management, AnnouncementAttachmentStore $attachments): RedirectResponse
    {
        $request->validate(['confirm_delete' => ['accepted']]);

        try {
            $unlinkedAttachment = $management->deleteAnnouncement($id, $this->user());
        } catch (AdminDataUnavailable|AdminOperationFailed) {
            return back()->withErrors(['announcements' => 'Não foi possível remover o comunicado.'])->withInput();
        }

        // The row is already committed; a file that cannot be removed is left to announcements:prune-orphans.
        if ($unlinkedAttachment !== null) {
            $attachments->delete($unlinkedAttachment);
        }

        return back()->with('status', 'Comunicado removido e registado na auditoria.');
    }

    private function user(): AdminUser
    {
        $user = auth('admin')->user();

        abort_unless($user instanceof AdminUser, 403);

        return $user;
    }

    private function toCents(string $value): int
    {
        [$euros, $decimals] = array_pad(preg_split('/[,.]/', $value) ?: [], 2, '');

        return ((int) $euros * 100) + (int) str_pad($decimals, 2, '0');
    }

    /** @return array{sector?:string,minimum_squares:int,prize_pool:string} */
    private function validatedPrizeRule(Request $request, bool $withSector): array
    {
        $rules = [
            'minimum_squares' => ['required', 'integer', 'min:0', 'max:10000000'],
            'prize_pool' => ['required', 'string', 'regex:/^\d{1,9}(?:[,.]\d{1,2})?$/'],
        ];

        if ($withSector) {
            $rules['sector'] = ['required', Rule::in(['pvc', 'aluminio'])];
        }

        /** @var array{sector?:string,minimum_squares:int,prize_pool:string} $data */
        $data = $request->validate($rules);

        return $data;
    }

    private function localDateTime(string $value): string
    {
        return (new DateTimeImmutable($value, new DateTimeZone((string) config('production.timezone'))))
            ->format('Y-m-d H:i:s');
    }

    /** @return array{scope:string,title:string,body?:string|null,starts_at:string,ends_at?:string|null} */
    private function validatedAnnouncement(Request $request, bool $isUpdate): array
    {
        $maxKilobytes = (int) config('announcements.attachments.max_kilobytes');
        $sizeMessage = sprintf('A imagem excede o limite de %d MB.', intdiv($maxKilobytes, 1024));
        $rules = [
            'scope' => ['required', Rule::in(['global', 'pvc', 'aluminio'])],
            'title' => ['required', 'string', 'max:160'],
            'body' => ['nullable', 'string', 'max:8000'],
            'starts_at' => ['required', 'date_format:Y-m-d\TH:i'],
            'ends_at' => ['nullable', 'date_format:Y-m-d\TH:i', 'after:starts_at'],
            'attachment' => ['nullable', 'file', 'max:'.$maxKilobytes, 'mimetypes:'.implode(',', AnnouncementFile::IMAGE_MIME_TYPES)],
        ];

        if ($isUpdate) {
            $rules['remove_attachment'] = ['nullable', 'boolean'];
            $rules['has_attachment'] = ['nullable', 'boolean'];
        }

        /** @var array{scope:string,title:string,body?:string|null,starts_at:string,ends_at?:string|null} $data */
        $data = $request->validate($rules, [
            'attachment.uploaded' => $sizeMessage.' Se for menor, tente enviar novamente.',
            'attachment.file' => 'O envio da imagem falhou. Tente novamente.',
            'attachment.max' => $sizeMessage,
            'attachment.mimetypes' => 'Formato não suportado. Use uma imagem JPG, PNG ou WEBP.',
        ]);

        // The repository enforces the same rule; this only gives a clear message first.
        $keepsAttachment = $isUpdate && $request->boolean('has_attachment') && ! $request->boolean('remove_attachment');

        if (trim((string) ($data['body'] ?? '')) === '' && ! $request->hasFile('attachment') && ! $keepsAttachment) {
            throw ValidationException::withMessages(['body' => 'Escreva uma mensagem ou anexe uma imagem.']);
        }

        return $data;
    }

    /** @throws AnnouncementAttachmentRejected */
    private function storedAttachment(Request $request, AnnouncementAttachmentStore $attachments): ?AnnouncementFile
    {
        $upload = $request->file('attachment');

        return $upload instanceof UploadedFile ? $attachments->store($upload) : null;
    }

    private function attachmentMessage(AnnouncementAttachmentRejected $exception): string
    {
        return match ($exception->reason) {
            AnnouncementAttachmentRejected::TOO_LARGE => sprintf(
                'A imagem excede o limite de %d MB.',
                intdiv((int) config('announcements.attachments.max_kilobytes'), 1024),
            ),
            AnnouncementAttachmentRejected::UNSUPPORTED_TYPE => 'Formato não suportado. Use uma imagem JPG, PNG ou WEBP.',
            AnnouncementAttachmentRejected::NOT_AN_IMAGE => 'O ficheiro enviado não é uma imagem válida.',
            AnnouncementAttachmentRejected::STORAGE_FAILED => 'Não foi possível guardar a imagem no servidor. Nenhuma alteração foi confirmada.',
            default => 'O envio da imagem falhou. Tente novamente.',
        };
    }
}
