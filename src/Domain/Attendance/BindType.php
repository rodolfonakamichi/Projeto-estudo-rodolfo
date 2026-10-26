<?php

declare(strict_types=1);

namespace App\Domain\Attendance;

/**
 * A que a comanda está presa (doc 02 §3.1). Valores em português porque são o
 * vocabulário do salão e vão assim para o banco/API.
 */
enum BindType: string
{
    case Table = 'MESA';
    case Counter = 'BALCAO';
    case Customer = 'CLIENTE';
    case Room = 'QUARTO';
    case Wristband = 'PULSEIRA';
    case Event = 'EVENTO';
    case Vehicle = 'VEICULO';
    case Service = 'SERVICO';
    case Walkin = 'AVULSO';
}
