<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Representa uma mensagem de email registrada no sistema.
 *
 * @property int $id A chave primária da mensagem.
 * @property string $assunto O assunto do email.
 * @property Carbon $criacao_registro_mensagem A data e hora em que o registro foi criado no sistema.
 * @property string $email_referente O email de referência ou remetente.
 * @property Carbon $envio_mensagem A data e hora em que o email foi efetivamente enviado.
 * @property string $mensagem O corpo da mensagem.
 * @property string $email_destinatario O email do destinatário.
 * @property Carbon|null $created_at Timestamp de criação do registro (padrão Eloquent).
 * @property Carbon|null $updated_at Timestamp da última atualização do registro (padrão Eloquent).
 */
class Mensagem extends Model
{
    // O conteúdo do seu model, como relacionamentos e outras lógicas, viria aqui.
}