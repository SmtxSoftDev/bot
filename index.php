<?php
include 'phpQuery.php';
define("TOKEN", "6979237761:AAF43fdiNjNdvjgfaG3ffzU-NXsfG9W-sIc");
$bot = new Bot();
$bot->Start();

class Bot
{
    private $url = "https://lalafo.kg/bishkek/kvartiry/arenda-kvartir/dolgosrochnaya-arenda-kvartir/1-bedroom/2-bedrooms/3-bedrooms/4-bedrooms/5-bedrooms/studio/6-bedroom?price[from]=10000&price[to]=1000000%C2%A4cy=KGS&sort_by=newest";

    private $type = 1;

    private $lalafoUrl = "https://lalafo.kg";

    private function GetJsonData($url)
    {
        $arrContextOptions=array(
            "ssl"=>array(
                "verify_peer"=>false,
                "verify_peer_name"=>false,
            ),
        ); 
        $doc = phpQuery::newDocument(@file_get_contents($url, false, stream_context_create($arrContextOptions)));
        foreach ($doc->find("script") as $script) {
            if (pq($script)->attr("id") == "__NEXT_DATA__") {
                $json = pq($script)->html();
                break;
            }
        }
        return json_decode($json);
    }

    public function Start()
    {
        while(true)
        {
            try {
                
                $users = $this->GetUsers();            
                if(empty($users))
                    return;

                for ($i = 1; $i <= 1; $i++) {
                    $url = $this->url . "&page=" . $i;
                    $json = "";
                    $jsonData = $this->GetJsonData($url);
                    $list = $jsonData->props->initialState->listing->listingFeed->data->items;
                    foreach ($list as $item) {
                        $index = 0;
                        $DataId = $item->id;
                        $CategoryId = $item->category_id;
                        $Title = $item->title;
                        $Description = $item->description;
                        $Price = $item->price . "" . $item->currency;
                        $Mobile = $item->mobile;
                        $InsertDate = date("y-m-d H:m:s");
                        $CreateDate = date("y-m-d H:m:s", $item->created_time);
                        $UpdateDate = date("y-m-d H:m:s", $item->updated_time);
                        $DataUrl = $this->lalafoUrl . $item->url;

                        if(!$this->IsAgency($Mobile) && !$this->IsDataAlreadySend($DataId)){
                            $this->InsertData($DataId);
                            foreach ($item->images as $image) {
                                if($index > 6)
                                    break;
                                if($index == 0){
                                    $files[] = array('type'=>'photo','media'=>$image->original_url, 'caption'=>'Цена: '.$Price.' '.$Description.' Телефон: '.$Mobile);
                                }else{
                                    $files[] = array('type'=>'photo','media'=>$image->original_url);
                                }
                                $index++;
                            }

                            foreach($users as $user)
                            {
                                if($this->GetUserByChatId($user["ChatId"])["IsActive"] == '1')
                                {
                                    $data = [
                                        'chat_id' => $user["ChatId"],
                                        'media' => json_encode($files)
                                    ];
                                    $res = $this->Request('sendMediaGroup', $data);
                                }
                            }
                            $files = [];
                            usleep(9000000);
                        }
                    }
                }
            } catch (Exception $ex) {
                echo $ex->getMessage();
            }
        }
    }

    private function Request($method, $data = [])
    {
        $ch = curl_init('https://api.telegram.org/bot'. TOKEN .'/'.$method);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        $res = curl_exec($ch);
        curl_close($ch);
        return json_decode($res, true);
    }

    private function GetUsers()
    {
        $count = 0;
        $results = [];
        $dataFromTelegram = $this->Request('getUpdates');
        foreach($dataFromTelegram["result"] as $result)
        {
            if(array_key_exists("message", $result))
            {
                $results[$count]["chat_id"] = $result["message"]["chat"]["id"];
                $results[$count]["username"] = $result["message"]["chat"]["username"];
                $results[$count]["first_name"] = $result["message"]["chat"]["first_name"];
                $count++;
            }
            if(array_key_exists("callback_query", $result))
            {
                $data = $result["callback_query"]["data"];
                $chatId = $result["callback_query"]["message"]["chat"]["id"];
                $this->UpdateUser($data, $chatId);
            }
        }
        $users = $this->RemoveDuplicatedDataFromArray($results);
        foreach($users as $user)
        {
            $this->AddUserIfNotExists($user['first_name'], $user['chat_id'], '');
        }
        return $this->GetUsersFromDb();
    }

    private function RemoveDuplicatedDataFromArray($array)
    {
        $duplicate_keys = array();
        $tmp = array();       
        foreach ($array as $key => $val){
            if (is_object($val))
                $val = (array)$val;

            if (!in_array($val, $tmp))
                $tmp[] = $val;
            else
                $duplicate_keys[] = $key;
        }
        foreach ($duplicate_keys as $key)
            unset($array[$key]);
        return array_values($array);
    }

    private function Test($data)
    {
        echo '<pre>';
        print_r($data);
        echo '</pre>';
    }

    private function connect()
    {
        $host = 'f94f5288a8d6';
        $port = '3306';
        $dbname = 'bot';
        $user = 'root';
        $pass = 'password';

        try {
            $dsn = "mysql:host=$host;port=$port";
            $pdo = new PDO($dsn, $user, $pass);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            $stmt = $pdo->query("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '$dbname'");
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$result) {
                $pdo->exec("CREATE DATABASE $dbname CHARACTER SET utf8 COLLATE utf8_general_ci");
            }

            $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8", $user, $pass);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            $pdo->exec("CREATE TABLE IF NOT EXISTS Users (
                            Id INT AUTO_INCREMENT PRIMARY KEY,
                            UserName VARCHAR(255),
                            ChatId VARCHAR(255),
                            RegisterDate DATETIME,
                            Conditions TEXT,
                            IsActive BOOLEAN
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci");

            $pdo->exec("CREATE TABLE IF NOT EXISTS Agency (
                            Mabile VARCHAR(50),
                            INDEX (Mabile)
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci");

            $pdo->exec("CREATE TABLE IF NOT EXISTS Data (
                            Id VARCHAR(25),
                            INDEX (Id)
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci");

            return $pdo;
        } catch(PDOException $e) {
            echo 'Подключение к базе данных не удалось: ' . $e->getMessage();
            return null;
        }
    }

    private function AddUserIfNotExists($userName, $chatId, $conditions)
    {
        $pdo = $this->connect();
        if ($pdo) {
            $existingUser = $this->GetUserByChatId($chatId);
            if (!$existingUser) {
                try {
                    $stmt = $pdo->prepare("INSERT INTO Users (UserName, ChatId, RegisterDate, Conditions, IsActive) VALUES (:userName, :chatId, NOW(), :conditions, :isActive)");
                    $stmt->execute([
                        'userName' => $userName,
                        'chatId' => $chatId,
                        'conditions' => $conditions,
                        'isActive' => 1
                    ]);
                    // $this->Button($chatId);
                    return true;
                } catch (PDOException $e) {
                    echo 'Ошибка добавления пользователя: ' . $e->getMessage();
                }
            }
        }
        return false;
    }

    private function UpdateUser($data, $chatId)
    {
        $pdo = $this->connect();
        if ($pdo) {
            try {
                $sql = "UPDATE Users SET Conditions = :conditions WHERE ChatId = :chatId";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    'conditions' => $data,
                    'chatId' => $chatId
                ]);

                return true;
            } catch (PDOException $e) {
                echo 'Ошибка обновления пользователя: ' . $e->getMessage();
                return false;
            }
        }
        return false;
    }

    private function GetUserByChatId($chatId)
    {
        $pdo = $this->connect();
        if ($pdo) {
            $stmt = $pdo->prepare('SELECT * FROM Users WHERE ChatId = :chatId');
            $stmt->execute(['chatId' => $chatId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($user) {
                $user['IsActive'] = (bool) $user['IsActive'];
                return $user;
            } else {
                return null;
            }
        }
        return null;
    }

    public function IsAgency($mobile)
    {
        $pdo = $this->connect();
        if ($pdo) {
            $stmt = $pdo->prepare('SELECT * FROM Agency WHERE Mobile = :mobile');
            $stmt->execute(['mobile' => $mobile]);
            $agency = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($agency)
                return true;
        }
        return false;
    }

    public function IsDataAlreadySend($Id)
    {
        $pdo = $this->connect();
        if ($pdo) {
            $stmt = $pdo->prepare('SELECT * FROM Data WHERE Id = :Id');
            $stmt->execute(['Id' => $Id]);
            $data = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($data)
                return true;
        }
        return false;
    }

    public function InsertData($Id)
    {
        $pdo = $this->connect();
        if ($pdo) {
            try {
                $stmt = $pdo->prepare('INSERT INTO Data (Id) VALUES (:Id)');
                $stmt->execute(['Id' => $Id]);
                return true;
            } catch (PDOException $e) {
                echo 'Ошибка при вставке данных: ' . $e->getMessage();
                return false;
            }
        }
        return false;
    }


    private function GetUsersFromDb()
    {
        $pdo = $this->connect();
        $users = [];
        if ($pdo) {
            $stmt = $pdo->query('SELECT * FROM Users');
            while ($row = $stmt->fetch()) {
                $row['IsActive'] = (bool) $row['IsActive'];
                $users[] = $row;
            }
        }
        return $users;
    }

    private function Button($chat_id)
    {
        $button = 
        [
            [
                ["text" => "10000", "callback_data" => "10000"],
                ["text" => "20000", "callback_data" => "20000"]
            ]
        ];

        $data = 
        [
            'chat_id' => $chat_id,
            'text' => 'Выберите желаемую цену!',
            'reply_markup' => json_encode(['inline_keyboard' => $button])
        ];

        $this->Request('sendMessage', $data);
    }
}