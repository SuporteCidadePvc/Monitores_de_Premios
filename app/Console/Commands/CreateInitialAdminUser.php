<?php

namespace App\Console\Commands;

use App\Domain\Admin\AdminUserRepository;
use App\Domain\Admin\Exceptions\AdminDataUnavailable;
use Illuminate\Console\Command;
use Illuminate\Contracts\Hashing\Hasher;
use Throwable;

final class CreateInitialAdminUser extends Command
{
    protected $signature = 'admin:create {username : Identificador do utilizador} {--name= : Nome a apresentar}';

    protected $description = 'Cria a primeira conta administrativa com password pedida de forma oculta.';

    public function handle(AdminUserRepository $users, Hasher $hasher): int
    {
        $username = trim((string) $this->argument('username'));
        $displayName = trim((string) ($this->option('name') ?: $username));

        if (! preg_match('/^[A-Za-z0-9._-]{3,64}$/', $username)) {
            $this->error('O utilizador deve ter 3 a 64 caracteres: letras, números, ponto, underscore ou hífen.');

            return self::FAILURE;
        }

        if ($displayName === '' || mb_strlen($displayName) > 128) {
            $this->error('O nome apresentado deve ter entre 1 e 128 caracteres.');

            return self::FAILURE;
        }

        $password = $this->secret('Password inicial (não será apresentada)');
        $confirmation = $this->secret('Repita a password');

        if (! is_string($password) || strlen($password) < 12) {
            $this->error('A password deve ter pelo menos 12 caracteres.');

            return self::FAILURE;
        }

        if ($password !== $confirmation) {
            $this->error('As passwords não coincidem.');

            return self::FAILURE;
        }

        try {
            $user = $users->createInitialAdministrator($username, $displayName, $hasher->make($password));
        } catch (\LogicException) {
            $this->error('Já existe uma conta administrativa. Nenhuma alteração foi efetuada.');

            return self::FAILURE;
        } catch (AdminDataUnavailable) {
            $this->error('Não foi possível criar a conta. Verifique a ligação administrativa e tente novamente.');

            return self::FAILURE;
        } catch (Throwable) {
            $this->error('Não foi possível criar a conta. Nenhuma password foi guardada em texto simples.');

            return self::FAILURE;
        }

        $this->info("Conta administrativa '{$user->username}' criada com sucesso.");

        return self::SUCCESS;
    }
}
