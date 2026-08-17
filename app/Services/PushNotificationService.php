<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

/**
 * Envio de notificação push via protocolo Web Push padrão (sem serviço de
 * terceiro tipo Firebase/OneSignal) — mesma lógica usada pelo navegador em
 * qualquer site com push nativo, servida pelo próprio back-end com um par de
 * chaves VAPID (config/services.php, `web_push`).
 */
class PushNotificationService
{
    public static function notifyUser(User $user, string $title, string $body, ?string $url = null): void
    {
        $subscriptions = $user->pushSubscriptions()->get();

        if ($subscriptions->isEmpty()) {
            return;
        }

        $webPush = new WebPush([
            'VAPID' => [
                'subject' => config('services.web_push.subject'),
                'publicKey' => config('services.web_push.public_key'),
                'privateKey' => config('services.web_push.private_key'),
            ],
        ]);

        $payload = json_encode(['title' => $title, 'body' => $body, 'url' => $url]);

        foreach ($subscriptions as $subscription) {
            $webPush->queueNotification(
                Subscription::create([
                    'endpoint' => $subscription->endpoint,
                    'publicKey' => $subscription->p256dh,
                    'authToken' => $subscription->auth,
                ]),
                $payload,
            );
        }

        try {
            foreach ($webPush->flush() as $report) {
                if ($report->isSuccess()) {
                    continue;
                }

                // Endpoint expirado ou inscrição revogada pelo navegador —
                // limpa do banco pra não tentar de novo nas próximas vezes.
                if ($report->isSubscriptionExpired()) {
                    $user->pushSubscriptions()
                        ->where('endpoint', $report->getRequest()->getUri())
                        ->delete();
                }
            }
        } catch (\Throwable $exception) {
            // Nunca deixa uma falha de push quebrar o fluxo principal (ex:
            // criação de transação) — só registra pra investigar depois.
            Log::warning('Falha ao enviar notificação push', ['error' => $exception->getMessage()]);
        }
    }
}
