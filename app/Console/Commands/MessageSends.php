<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Log;
use App\Models\User;
use App\Models\Messages;
use \Carbon\Carbon;
use GuzzleHttp\Client;

use Notification;
use App\Notifications\AlertNotification;

class MessageSends extends Command
{
    protected $signature = 'messages:cron';
    protected $description = 'Command description';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {

        Log::info('Cron messages start');

        $users = User::with('sessions')
            ->whereNotNull('server_whatsapp')
            ->orderBy('last_login', 'desc')
            ->with('messagesNaoEnviadas')
            ->get();

        foreach ($users as $user) {

            foreach ($user->sessions as $session) {

                $getHostDevice = json_decode($this->requestIntegracao($user, $session, [
                    "session" =>  $session->session_name,
                ], 'getHostDevice'), true, JSON_UNESCAPED_UNICODE);

                if(isset($getHostDevice['result'])){

                    if ($getHostDevice['result'] == 401) {

                        echo "Mensagem:: Mensagem não enviada, sessão {$session->session_name} DISCONNECTED \n";

                        $update = User::findOrFail($user->id);

                        $end_date = isset($update->last_activity) ? $update->last_activity : Carbon::now()->format('Y-m-d H:i:s');
                        $dataFim = Carbon::now()->diffInHours($end_date);

                        if ($dataFim > 3 and $user->block_mail != 1) {
                            Notification::send($user, new AlertNotification([
                                'subject' => 'Falha ao enviar mensagens',
                                'title' => 'Sua sessão se encontra fechada',
                                'body' => 'Suas mensagens não foram enviadas, pois a sessão atual está fechada, inicie uma sessão e aguarde.',
                                'button' => 'Iniciar uma sessão',
                                'footer' => 'Esse aviso é automático, obrigado por utilizar o DivulgaWhatsApp!',
                                'actionURL' => url('/painel/sessions'),
                            ]));
                        }

                        $sleep = rand(1, 60);
                        sleep($sleep);
                        echo "Esperando:: {$sleep} segundos... \n";

                    } else {

                        switch ($getHostDevice['result']) {
                            case 200:

                                if ($user->messagesNaoEnviadas->count() > 0) {

                                    foreach ($user->messagesNaoEnviadas as $message) {

                                        //se for texto
                                        if ($message->type == 'Texto') {

                                            //sem agendamento
                                            if ($message->schedule == 'false' or $message->schedule == 'nao' and empty($message->date_schedule_send)) {
                                                $payload = json_decode($this->requestIntegracao($user, $session, [
                                                    'session' => $session->session_name,
                                                    'number' => $message->number,
                                                    'text' => $message->message
                                                ], 'sendText'), true, JSON_UNESCAPED_UNICODE);

                                                if(isset($payload)){
                                                    $update = Messages::find($message->id);
                                                    $update->update([
                                                        'send_at' => Carbon::now(),
                                                        'status' => 'Enviado'
                                                    ]);
                                                    echo "Mensagem:: Mensagem enviada, sessão CONNECTED \n";
                                                }
                                            }

                                            //se for agendado
                                            if ($message->schedule == 'sim' and Carbon::now()->format('Y-m-d H:i:s') >= $message->date_schedule_send) {

                                                Log::info('Agendado para enviar: ' . $message->id);

                                                $payload = json_decode($this->requestIntegracao($user, $session, [
                                                    'session' => $session->session_name,
                                                    'number' => $message->number,
                                                    'text' => $message->message
                                                ], 'sendText'), true, JSON_UNESCAPED_UNICODE);

                                                if(isset($payload)){
                                                    $update = Messages::find($message->id);
                                                    $update->update([
                                                        'send_at' => Carbon::now(),
                                                        'status' => 'Enviado'
                                                    ]);
                                                    echo "Mensagem:: Mensagem enviada, sessão CONNECTED \n";
                                                }
                                            }

                                        //se for arquivo
                                        } else {

                                            //sem agendamento
                                            if ($message->schedule == 'false' or $message->schedule == 'nao' and empty($message->date_schedule_send)) {
                                                $payload = json_decode($this->requestIntegracao($user, $session, [
                                                    'session' => $session->session_name,
                                                    'number' => $message->number,
                                                    'path' => isset($message->path) ? $message->path : '',
                                                    'caption' => $message->message,
                                                ], 'sendFile64'), true, JSON_UNESCAPED_UNICODE);

                                                if(isset($payload)){
                                                  $update = Messages::find($message->id);
                                                    $update->update([
                                                        'send_at' => Carbon::now(),
                                                        'status' => 'Enviado'
                                                    ]);
                                                    echo "Mensagem:: Mensagem enviada, sessão CONNECTED \n";
                                                }
                                            }

                                            //se for agendado
                                            if ($message->schedule == 'sim' and Carbon::now()->format('Y-m-d H:i:s') >= $message->date_schedule_send) {
                                                Log::info('Agendado para enviar: ' . $message->id);

                                                $payload = json_decode($this->requestIntegracao($user, $session, [
                                                    'session' => $session->session_name,
                                                    'number' => $message->number,
                                                    'path' => isset($message->path) ? $message->path : '',
                                                    'caption' => $message->message,
                                                ], 'sendFile64'), true, JSON_UNESCAPED_UNICODE);

                                                if(isset($payload)){
                                                    $update = Messages::find($message->id);
                                                    $update->update([
                                                        'send_at' => Carbon::now(),
                                                        'status' => 'Enviado'
                                                    ]);
                                                    echo "Mensagem:: Mensagem enviada, sessão CONNECTED \n";
                                                }
                                            }
                                        }

                                        // espera alguns segundos para enviar a próxima mensagem
                                        $sleep = rand(1, 60);
                                        sleep($sleep);
                                        echo "Esperando:: {$sleep} segundos... \n";
                                        echo "Mensagem:: Mensagem enviada, sessão CONNECTED \n";
                                    }

                                    $end_date = isset($update->last_activity) ? $update->last_activity : Carbon::now()->format('Y-m-d H:i:s');
                                    $dataFim = Carbon::now()->diffInMinutes($end_date);

                                    if ($dataFim > 30 and $user->block_mail != 1) {
                                        Notification::send($user, new AlertNotification([
                                            'subject' => 'Envio de mensagens finalizado',
                                            'title' => 'Envio de mensagens finalizado',
                                            'body' => 'Suas mensagens foram enviadas, obrigado por utilizar nossos serviços.',
                                            'button' => 'Ver histórico',
                                            'footer' => 'Esse aviso é automático',
                                            'actionURL' => url('/painel/message'),
                                        ]));
                                    }
                                    echo "Mensagem:: Envio finalizado \n";
                                }else{

                                    echo "Sessão:: ".$session->session_name."\nAvisos:: Não há mensagens para enviar \n";
                                    echo "Mensagens não enviadas:: ".$user->messagesNaoEnviadas->count()."\n";
                                }

                                Log::notice($session->session_name . ' -> CONNECTED');

                                break;

                            case 401:
                                echo "Mensagem:: Sessão inválida QRCODE\n";
                                Log::notice($session->session_name . ' -> QRCODE');
                                break;

                            default:
                                echo "Mensagem:: Erro ao enviar mensagem DESCONECTADA\n";
                                Log::notice($session->session_name . ' -> DESCONECTADA');
                                break;
                        }

                        $sleep = rand(1, 15);
                        sleep($sleep);
                        echo "Esperando:: {$sleep} segundos... \n";
                        Log::debug($getHostDevice);
                    }

                }else{

                    echo "Sessão::".$session->session_name."\nMensagem:: Não há sessões para enviar \n";
                    var_dump($getHostDevice);
                }

            }

            $end_date = isset($update->last_activity) ? $update->last_activity : Carbon::now()->format('Y-m-d H:i:s');
            $dataFim = Carbon::now()->diffInHours($end_date);

            if(count($user->messagesNaoEnviadas) > 0){
                if ($dataFim > 3 and $user->block_mail != 1) {
                    Notification::send($user, new AlertNotification([
                        'subject' => 'Mensagens não enviadas',
                        'title' => 'Envio de mensagens não finalizado',
                        'body' => 'Suas mensagens não foram enviadas, sua sessão está off-line.',
                        'button' => 'Gerenciar sessões',
                        'footer' => 'Esse aviso é automático',
                        'actionURL' => url('/painel/sessions'),
                    ]));
                }
            }

        }

        Log::info('Cron messages finished');

        return true;
    }

    public function requestIntegracao($user, $session, $body, $action)
    {

        try {

            $host = isset($user->server_whatsapp) ? $user->server_whatsapp : false;
            $client = new Client(['base_uri' => $host]);

            $header = ["Content-Type" => "application/json", "sessionkey" => $session->session_key ?? ""];
            $response = $client->post($action, ["verify" => false, "body" => json_encode($body), "headers" => $header,]);

            $body = $response->getBody();

            return $body;
        } catch (\GuzzleHttp\Exception\ClientException $e) {

            $response = $e->getResponse();
            return (string)($response->getBody());
        }
    }
}
