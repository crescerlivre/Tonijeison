<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Sessions;
use DataTables;
use Carbon\Carbon;
use Log;
//use \Yajra\DataTables\Facades\DataTables;
class SessionsController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {

        try {

            if($request->user()->can('sessoes-todas')) {

                $sessions = Sessions::orderBy('created_at', 'DESC')
                ->with('user')
                ->paginate(10);

            }else{

                $sessions = Sessions::orderBy('created_at', 'DESC')
                ->with('user')->where('user_id', $request->user()->id)
                ->paginate(10);

            }

            return view('sessions.index')->with('sessions', $sessions);

        } catch (\Throwable $th) {
            throw $th;
        }

    }

    public function datatables (Request $request)
    {

        try {

            if($request->user()->can('sessoes-todas')) {

                $sessions = Sessions::orderBy('created_at', 'DESC')
                ->with('user')
                ->get();

            }else{

                $sessions = Sessions::orderBy('created_at', 'DESC')
                ->with('user')->where('user_id', $request->user()->id)
                ->get();

            }

            return DataTables::of($sessions)->make(true);

        } catch (\Throwable $th) {
            throw $th;
        }

    }

    public function create(Request $request)
    {

        $sessions = Sessions::where('user_id', $request->user()->id)->count();

        if($sessions > 0){
            return redirect()->route('sessions.index')->withSuccess('Limite de sessões atingido!');
        }

        $sessions = Sessions::get();
        return view('sessions.create', compact('sessions'));
    }

    public function store(Request $request)
    {
        try {

            $request->merge(['user_id' => $request->user()->id]);

            Sessions::create($request->all());
            return redirect()->route('sessions.index')->withSuccess('Session created successfully');

        } catch (\Throwable $th) {
            \Log::critical(['Erro ao criar sessão', $th->getMessage()]);
            return redirect()->route('sessions.index')
            ->withErrors('Problema ao criar a sessão!');
        }

    }

    public function start(Request $request)
    {
        try {

            $session = Sessions::where('session_name', $request->session_name)
            ->where('session_key', $request->session_key)
            ->where('user_id', $request->user()->id)
            ->first();

            $checkSession = json_decode($this->requestIntegracao($request, $session, [
                'session' => $session->session_name,
            ], 'getHostDevice'), true, JSON_UNESCAPED_UNICODE);

            Log::info(['status da sessao', $checkSession]);

            if( isset($checkSession) and isset($checkSession['status']) )
            {
                \Log::notice(['Start uma sessão', $session]);
                switch ($checkSession['status']) {
                    case 'Disconnected':

                        $start = json_decode(self::requestIntegracao($request, $session, [
                            "session" => $session->session_name,
                            "wh_connect" => $session->webhook_wh_connect ?? '',
                            "wh_qrcode" => $session->webhook_qr_code ?? '',
                            "wh_status" => $session->webhook_wh_status ?? '',
                            "wh_message" => $session->webhook_wh_message ?? ''
                        ], 'start'), true, JSON_UNESCAPED_UNICODE);

                        $update = Sessions::find($session->id);
                        $update->update([ 'status' => $checkSession['status'] ? $checkSession['status'] : 'DISCONNECTED' ]);
                        \Log::notice(['Callback do start', $start]);

                    return response()->json($start);
                }
            }

            if( isset($checkSession) and isset($checkSession['result']))
            {
                \Log::notice(['Callback do start', $checkSession]);
                if( intVal($checkSession['result']) == 200 ){

                    $host = json_decode($this->requestIntegracao($request, $session, [
                        'session' => $session->session_name ?? '',
                    ], 'getHostDevice'), true, JSON_UNESCAPED_UNICODE);

                    $update = Sessions::find($session->id);
                    $update->update([ 'status' => 'CONNECTED']);

                    $session->update([
                        'ip_host' => self::getIp() ?? '',
                        'last_connected' => Carbon::now(),
                        'connected' => $host['connected'] ?? '',
                        'locales' => $host['locales'] ?? '',
                        'number' => $host['number'] ?? '',
                        'device_manufacturer' => $host['phone']['device_manufacturer'] ?? '',
                        'device_model' => $host['phone']['device_model'] ?? '',
                        'mcc' => $host['phone']['mcc'] ?? '',
                        'mnc' => $host['phone']['mnc'] ?? '',
                        'os_build_number' => $host['phone']['os_build_number'] ?? '',
                        'os_version' => $host['phone']['os_version'] ?? '',
                        'wa_version' => $host['phone']['wa_version'] ?? '',
                        'pushname' => $host['pushname'] ?? '',
                        'result' => $host['result'] ?? ''
                    ]);

                    return response()->json([
                        'error' => false,
                        'message' => 'Sessão já foi iniciada, aguarde até 60 segundos para sincronização dos status...',
                        'checksession' => $checkSession,
                        'sessions' => $session
                    ], 200);
                }
            }

            $start = json_decode(self::requestIntegracao($request, $session, [
                "session" => $session->session_name,
                "wh_connect" => $session->webhook_wh_connect ?? '',
                "wh_qrcode" => $session->webhook_qr_code ?? '',
                "wh_status" => $session->webhook_wh_status ?? '',
                "wh_message" => $session->webhook_wh_message ?? ''
            ], 'start'), true, JSON_UNESCAPED_UNICODE);

            $update = Sessions::find($session->id);
            $update->update([
                'status' => 'CONNECTED'
            ]);

            return response()->json([
                'error' => false,
                'message' => 'Sua sessão foi iniciada com sucesso, aguarde.',
                'checksession' => $checkSession,
                'sessions' => $session
            ], 200);

        } catch (\Throwable $th) {
            \Log::critical(['Erro ao iniciar sessão', $th->getMessage()]);
            return response()->json(['error' => true, 'message' => $th->getMessage()], 500);
        }

    }

    public function onlineShow(Request $request, $id)
    {
        try {

            if($request->user()->can('sessoes-todas')) {

                $session = Sessions::findOrFail($id);

            }else{

                $session = Sessions::whereId($id)
                ->where('user_id', $request->user()->id)
                ->first();

            }

            if(!isset($session)) {
                return response()->json(['message' => 'Sessão não encontrada'], 500);
            }

            $host = json_decode($this->requestIntegracao($request, $session, [
                'session' => $session->session_name ?? '',
            ], 'getHostDevice'), true, JSON_UNESCAPED_UNICODE);

            $ip = self::getIp() !== null ? self::getIp() : null;
            $location = json_encode(geoip($ip));

            $session->update([
                'location' => $location ?? '',
                'ip_host' => self::getIp() ?? '',
                'last_connected' => Carbon::now(),
                'connected' => $host['connected'] ?? '',
                'locales' => $host['locales'] ?? '',
                'number' => $host['number'] ?? '',
                'device_manufacturer' => $host['phone']['device_manufacturer'] ?? '',
                'device_model' => $host['phone']['device_model'] ?? '',
                'mcc' => $host['phone']['mcc'] ?? '',
                'mnc' => $host['phone']['mnc'] ?? '',
                'os_build_number' => $host['phone']['os_build_number'] ?? '',
                'os_version' => $host['phone']['os_version'] ?? '',
                'wa_version' => $host['phone']['wa_version'] ?? '',
                'pushname' => $host['pushname'] ?? '',
                'result' => $host['result'] ?? ''
            ]);

            return response()->json($host);

        } catch (\Throwable $th) {

            \Log::critical(['Falha getInfos session', $th->getMessage()]);
            throw $th;

        }

    }

    public function edit($id)
    {
        try {

            $session = Sessions::findOrFail($id);
            return view('sessions.edit')->with('session', $session);

        } catch (\Throwable $th) {
            throw $th;
        }

    }

    public function update(Request $request, $id)
    {
        try {

            $this->validate($request, [
                'session_name' => 'required',
                'session_key' => 'required',
            ]);

            $session = Sessions::findOrFail($id);
            $request->merge(['user_id' => $request->user()->id]);

            $session->update($request->all());

            return redirect()->route('sessions.index')
                            ->withSuccess('success','A sessão foi atualizada com sucesso.');
        } catch (\Throwable $th) {
            return redirect()->route('sessions.index')
                        ->withErrors('Problema ao atualizar a sessão!');
        }

    }

    public function stop(Request $request, $id)
    {
        try {

            $session = Sessions::where('user_id', $request->user()->id)->whereId($id)->first();

            if(isset($session->session_name)){

                $close = json_decode(self::requestIntegracao($request, $session, [
                    "session" => $session->session_name,
                ], 'close'));

                self::requestIntegracao($request, $session, [
                    "session" => $session->session_name,
                ], 'deleteSession');

                $update = Sessions::find($session->id);
                $update->update([
                    'status' => 'DESCONECTADA'
                ]);

                return response()->json(['success' => true, 'message' => 'Sessão DISCONECTED success!' ?? '']);
            }

            $update = Sessions::find($session->id);
            $update->update([
                'status' => 'DESCONECTADA'
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Sessão não existe'
            ]);

        } catch (\Throwable $th) {

            return response()->json([
                'success' => false,
                'message' => $th->getMessage()
            ]);
        }


    }

    public function destroy(Request $request, $id)
    {
        try {

            $session = Sessions::findOrFail($id);

            self::requestIntegracao($request, $session, [
                "session" => $session->session_name,
            ], 'close');

            self::requestIntegracao($request, $session, [
                "session" => $session->session_name,
            ], 'deleteSession');

            $session->delete();

            return redirect()->route('sessions.index')
                        ->withSuccess('Sessão deletada com sucesso!');

        } catch (\Throwable $th) {

            return redirect()->route('sessions.index')
                        ->withErrors('Problema ao deletar a sessão!');

        }
    }
}
