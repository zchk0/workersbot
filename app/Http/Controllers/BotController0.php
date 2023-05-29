<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

use WeStacks\TeleBot\TeleBot;
use WeStacks\TeleBot\Objects\Update;
use WeStacks\TeleBot\Objects\Keyboard;

use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Storage;
use App\Bot\EmployeesAndDismissedExport;

use App\Models\BotSession;
use Illuminate\Support\Facades\Session;
use Carbon\Carbon;



class BotController0 extends Controller
{
    public function handle(Request $request)
    {
        $bot = new TeleBot([
            'token' => 'XXXXXX',
        ]);
        $update = new Update($request->all());
        
        // если это callback кнопки
        if (isset($update->callback_query)) {
            $callback_query = $update->callback_query;
            $callback_data = $callback_query->data;
            
            $message = $callback_query->message;
            $chat_id = $callback_query->message->chat->id;
            $message_id = $callback_query->message->message_id;
            
            if (substr($callback_data, 0, 7) === 'delete_') {
                $employee_id = substr($callback_data, 7);
                
                // проверка на наличие базе данных
                $ifemployeeExists = DB::table('list_employees')->where('id', $employee_id)->exists();
                if($ifemployeeExists){
                   // Удалить сотрудника по id и добавить в список уволенных
                    $worker_now = DB::table('list_employees')->where('id', $employee_id)->first();
                    DB::insert('INSERT INTO list_dismissed (fio, position, phone, date, date_delete) values (?, ?, ?, ?, ?)', [$worker_now->fio, $worker_now->position, $worker_now->phone, $worker_now->date, Carbon::now()->tz('Asia/Bangkok')]);
                    DB::table('list_employees')->where('id', '=', $employee_id)->delete();
                
                    $bot->editMessageText([
                        'chat_id' => $chat_id,
                        'message_id' => $message_id,
                        'text' => 'Сотрудник был перемещен в список уволенных.'
                    ]);
                } 
                else {
                    $bot->editMessageText([
                        'chat_id' => $chat_id,
                        'message_id' => $message_id,
                        'text' => 'Сотрудник уже не существует.'
                    ]);
                }
                
            }
            if (substr($callback_data, 0, 7) === 'return_') {
                
                $employee_id = substr($callback_data, 7);
                
                // проверка на наличие базе данных
                $ifemployeeExists = DB::table('list_dismissed')->where('id', $employee_id)->exists();
                if($ifemployeeExists){
                // Вернуть сотрудника по id и добавить в штат
                $worker_now = DB::table('list_dismissed')->where('id', $employee_id)->first();
                DB::insert('INSERT INTO list_employees (fio, position, phone, date, date_add) values (?, ?, ?, ?, ?)', [$worker_now->fio, $worker_now->position, $worker_now->phone, $worker_now->date, Carbon::now()->tz('Asia/Bangkok')]);
                DB::table('list_dismissed')->where('id', '=', $employee_id)->delete();
                
                $bot->editMessageText([
                    'chat_id' => $chat_id,
                    'message_id' => $message_id,
                    'text' => 'Сотрудник был возвращен в штат.'
                ]);
                } 
                else {
                    $bot->editMessageText([
                        'chat_id' => $chat_id,
                        'message_id' => $message_id,
                        'text' => 'Сотрудник уже не существует.'
                    ]);
                }  
            }
            return;
        }
        else{
            $message = $update->message;
            $chat_id = $message->chat->id;
        }
        
        $session = BotSession::firstOrNew(['user_id' => $chat_id]);
        $state = $session->state ?? '';
        $data = $session->data ?? [];
        
        if (isset($message->text)) {
        if ($message->text == '/start') {
            $session->delete();
            
            $bot->sendMessage([
                'chat_id' => $chat_id,
                'text' => "<i>Бот написан на стеке LEMP+laravel \nБаза данных: PostgreSQL \nБилиотека экспорта в EXCEL: maatwebsite/excel \nБилиотека взаимодействия с ботом: westacks/telebot</i> \n\n<b>Чтобы использовать бота выберите действие из меню</b>",
                'parse_mode' => 'HTML',
                'reply_markup' => [
                    'keyboard' => [
                        [['text' => 'Добавить сотрудника'],['text' => 'Список сотрудников']],
                        [['text' => 'Список уволенных'],['text' => 'Выгрузить в Excel']]
                    ],
                    'resize_keyboard' => true
                ]
            ]);
            
            return;
        } 
        elseif ($message->text == 'Добавить сотрудника') {
            $session->state = 'asking_name';
            $session->save();
            
            $bot->sendMessage([
                'chat_id' => $chat_id,
                'text' => 'Пожалуйста, введите ФИО нового сотрудника.'
            ]);
            return;
        } 
        elseif ($message->text == 'Список сотрудников') {
            $session->delete();
            
            // проверка на наличие базе данных
            $hasEmployees = DB::table('list_employees')->exists();
            if ($hasEmployees) {
                
            $bot->sendMessage([
                'chat_id' => $chat_id,
                'text' => 'Список текущих сотрудников:'
            ]);
            
            $list_employees = DB::table('list_employees')->orderBy('fio', 'asc')->get();

            // Обойти каждого сотрудника и отправить информацию о нем
            foreach ($list_employees as $employee) {
                $bot->sendMessage([
                'chat_id' => $chat_id,
                'text' => "Сотрудник: {$employee->fio} \nДолжность: {$employee->position} \nТелефон: {$employee->phone} \nДата рождения: {$employee->date} \nДата трудоустройства: {$employee->date_add} (UTC+7)",
                'reply_markup' => [
                'inline_keyboard' => [
                        [['text' => 'Уволить', 'callback_data' => 'delete_' . $employee->id]],
                    ]
                ],
                ]);
            }
            }
            else {
                $bot->sendMessage(['chat_id' => $chat_id,'text' => 'Сотрудников в штате нет.']);
            }
            return;
        }
        elseif ($message->text == 'Список уволенных') {
            $session->delete();
            
            $hasEmployees = DB::table('list_dismissed')->exists();
            if ($hasEmployees) {
        
            $bot->sendMessage([
                'chat_id' => $chat_id,
                'text' => 'Список уволенных:'
            ]);
            
            $list_dismissed = DB::table('list_dismissed')->orderBy('fio', 'asc')->get();

            // Обойти каждого сотрудника и отправить информацию о нем
            foreach ($list_dismissed as $employee) {
                $bot->sendMessage([
                'chat_id' => $chat_id,
                'text' => "Сотрудник: {$employee->fio} \nДолжность: {$employee->position} \nТелефон: {$employee->phone} \nДата рождения: {$employee->date} \nДата увольнения: {$employee->date_delete} (UTC+7)",
                'reply_markup' => [
                'inline_keyboard' => [
                    [['text' => 'Вернуть на работу', 'callback_data' => 'return_' . $employee->id]],
                    ]
                ],
                ]);
            }
            }
            else {
                $bot->sendMessage(['chat_id' => $chat_id,'text' => 'Уволенных сотрудников нет.']);
            }
            return;
        }
        elseif ($message->text == 'Выгрузить в Excel') {
            $session->delete();
            
            $bot->sendMessage([
                'chat_id' => $chat_id,
                'text' => 'Файл Excel со списком текущих сотрудников и уволенных (2 листа).'
            ]);
            
            $fileName = 'employees_and_dismissed.xlsx';
            Excel::store(new EmployeesAndDismissedExport, $fileName, 'public');
            $filePath = Storage::disk('public')->path($fileName);

            // Отправка файла пользователю и удаление файла после отправки
            $bot->sendDocument(['chat_id' => $chat_id, 'document' => $filePath,]);
            Storage::disk('public')->delete($fileName);
            
            return;    
        }
        }
        if (!empty($state)) {
            if (!isset($message->text)) {
                $bot->sendMessage([
                    'chat_id' => $chat_id,
                    'text' => 'Пожалуйста, введите текстовое значение.'
                    ]);
            return;
            }

            switch ($state) {
                case 'asking_name':
                    // отклонить имя, так как оно содержит эмодзи
                    if (preg_match('/[\x{1000}-\x{10FFFF}]/u', $message->text) ) {
                        $bot->sendMessage(['chat_id' => $chat_id,'text' => 'Смайлы в ФИО запрещены.' ]);
                    } 
                    elseif(preg_match("/\b(?:(?:https?|ftp):\/\/|www\.)[-a-z0-9+&@#\/%?=~_|!:,.;]*[-a-z0-9+&@#\/%=~_|]/i", $message->text)) {
                        $bot->sendMessage(['chat_id' => $chat_id, 'text' => 'Извините, ссылки не допускаются в ФИО сотрудника.']);
                    }
                    elseif (mb_strlen($message->text, 'UTF-8') < 3 || mb_strlen($message->text, 'UTF-8') > 80){
                        $bot->sendMessage(['chat_id' => $chat_id,'text' => 'Длина ФИО не менее 3 символов и не более 80.' ]);
                    }
                    else {
                    // сохраняем имя и переходим к следующему шагу
                    $data['fio'] = $message->text;
                    $session->state = 'asking_position';
                    $session->data = $data;
                    $session->save();

                    $bot->sendMessage([
                        'chat_id' => $chat_id,
                        'text' => 'Введите должность сотрудника.' 
                    ]);
                    }
                    break;

                case 'asking_position':
                    // проверка ввода должности
                    if (preg_match('/[\x{1000}-\x{10FFFF}]/u', $message->text) ) {
                        $bot->sendMessage(['chat_id' => $chat_id,'text' => 'Смайлы в должности запрещены.' ]);
                        return;
                    } 
                    if (mb_strlen($message->text, 'UTF-8') < 3 || mb_strlen($message->text, 'UTF-8') > 80){
                        $bot->sendMessage(['chat_id' => $chat_id,'text' => 'Длина должности не менее 3 символов и не более 80.' ]);
                        return;
                    }
                    // сохраняем должность и переходим к следующему шагу
                    $data['position'] = $message->text;
                    $session->state = 'asking_phone';
                    $session->data = $data;
                    $session->save();

                    $bot->sendMessage([
                        'chat_id' => $chat_id,
                        'text' => 'Введите телефон сотрудника.'
                    ]);

                    break;

                case 'asking_phone':
                    // сохраняем телефон и переходим к следующему шагу
                    $phoneNumber = $message->text;
                    $phoneNumberPattern = '/^\+7[0-9]{10}$/';

                    if (!preg_match($phoneNumberPattern, $phoneNumber)) {
                        $bot->sendMessage([
                        'chat_id' => $chat_id,
                        'text' => 'Номер телефона должен быть в формате +79999999999.'
                        ]);
                        return;
                    }
                    
                    $data['phone'] = $message->text;
                    $session->state = 'asking_birthday';
                    $session->data = $data;
                    $session->save();

                    $bot->sendMessage([
                        'chat_id' => $chat_id,
                        'text' => 'Введите дату рождения сотрудника (dd.mm.yyyy).'
                    ]);

                    break;

                case 'asking_birthday':
                    // сохраняем дату рождения и завершаем процесс добавления
                    try {
                        $dateeb = $message->text;
                        
                        if (preg_match("/^(\d{2})\.(\d{2})\.(\d{4})$/", $dateeb, $matches)) {
                            if (!checkdate($matches[2], $matches[1], $matches[3])) {
                                throw new \InvalidArgumentException();
                            }    
                            $dateb = Carbon::createFromFormat('d.m.Y', $dateeb);
                        
                            if ($dateb->diffInYears(Carbon::now()) < 18) {
                                $bot->sendMessage([
                                    'chat_id' => $chat_id,
                                    'text' => 'Сотруднику должно быть 18 лет или больше.'
                                ]);
                                return;
                            }
                            elseif ($dateb->diffInYears(Carbon::now()) > 100) {
                                $bot->sendMessage([
                                    'chat_id' => $chat_id,
                                    'text' => 'Сотруднику должно не быть не более 100 лет.'
                                ]);
                                return;
                            }
                            if (!$dateb->isPast()) {
                                $bot->sendMessage([
                                'chat_id' => $chat_id,
                                'text' => 'Дата рождения не может быть больше текущей даты.'
                                ]);
                                return;
                            }
                            $data['birthday'] = $matches[0];
                        }
                        else {
                             throw new \InvalidArgumentException();
                        }
                        
                    } catch (\InvalidArgumentException $e) {
                        $bot->sendMessage([
                            'chat_id' => $chat_id,
                            'text' => 'Неверный формат даты. Пожалуйста, введите дату в формате dd.mm.yyyy.'
                        ]);
                        return;
                    }
                    $session->state = 'complete';
                    $session->data = $data;
                    $session->save();
                    
                    // сохраняем сотрудника в базе данных
                    DB::insert('INSERT INTO list_employees (fio, position, phone, date, date_add) values (?, ?, ?, ?, ?)', [$data['fio'], $data['position'], $data['phone'], $data['birthday'], Carbon::now()->tz('Asia/Bangkok')]);
                    
                    $bot->sendMessage([
                        'chat_id' => $chat_id,
                        'text' => "Сотрудник успешно добавлен! \n\nФИО:  <code>" . $data['fio'] . "</code> \nДата рождения:  <code>" . $data['birthday'] . "</code> \nДолжность:  <code>" . $data['position'] . "</code> \nТелефон: " . $data['phone']  . "\nДата трудоустройства: " . Carbon::now()->tz('Asia/Bangkok'),
                        'parse_mode' => 'HTML'
                    ]);
                    
                    $session->delete();
                    break;
                default:
                    $this->showKeyboard($bot, $chat_id);
                    break;
            }
        }
        else {$this->showKeyboard($bot, $chat_id);}

        return 'ok';
    }
    
    private function showKeyboard($bot, $chat_id)
    {
        $bot->sendMessage([
            'chat_id' => $chat_id,
            'text' => 'Чтобы использовать бота выберите действие из меню',
            'reply_markup' => [
                'keyboard' => [
                    [['text' => 'Добавить сотрудника'],['text' => 'Список сотрудников']],
                    [['text' => 'Список уволенных'],['text' => 'Выгрузить в Excel']]
                ],
                'resize_keyboard' => true
            ]
        ]);
    }
}
