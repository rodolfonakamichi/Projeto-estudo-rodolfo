<?php

declare(strict_types=1);

namespace App\Application\Identity\Auth;

use App\Application\Shared\Bus\CommandHandler;
use App\Domain\Identity\ApiTokenRepository;
use App\Domain\Identity\OpaqueToken;
use Psr\Clock\ClockInterface;

final readonly class LogoutHandler implements CommandHandler
{
    public function __construct(
        private ApiTokenRepository $tokens,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(Logout $command): void
    {
        $this->tokens->findByToken(OpaqueToken::fromPlain($command->plainToken))?->revoke($this->clock->now());
    }
}
