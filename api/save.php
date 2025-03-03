<?php
header("Content-Type: application/json; charset=UTF-8");
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', '.error.php'); // ログファイルのパスを指定してください
error_reporting(E_ALL);

$input = file_get_contents('php://input');
$data = json_decode($input, true);
if ($data === null) {
    echo json_encode(['error' => 'Invalid JSON']);
    exit;
}

// 設定ファイルの読み込み
$config = require 'config.php';

// 設定を変数に格納
$servername = $config['servername'];
$username = $config['username'];
$password = $config['password'];
$dbname = $config['dbname'];

try {
    $conn = new PDO("mysql:host=$servername;dbname=$dbname;charset=utf8", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // トランザクションの開始
    $conn->beginTransaction();

    // データの挿入または更新
    foreach ($data as $item) {
        try {
            // 既存のレコードを取得
            $existingRecord = $conn->prepare("
                SELECT * FROM possessions
                WHERE card_id = :card_id
            ");
            $existingRecord->execute([
                ':card_id' => $item['card_id']
            ]);
            $existingData = $existingRecord->fetch(PDO::FETCH_ASSOC);

            if ($existingData) {
                if ($existingData['possession_flg'] != $item['possession']) {
                    $stmt = $conn->prepare("
                        UPDATE possessions
                        SET possession_flg = :possession_flg, update_time = :update_time
                        WHERE card_id = :card_id
                    ");
                    $stmt->execute([
                        ':possession_flg' => $item['possession'],
                        ':card_id' => $item['card_id'], // card_idもWHERE句で指定
                        ':update_time' => date('Y-m-d H:i:s') // 現在の時刻をセット
                    ]);
                }
            } else {
                // 既存のレコードがない場合はINSERT
                $stmt = $conn->prepare("
                    INSERT INTO possessions (card_id, possession_flg, update_time)
                    VALUES (:card_id, :possession_flg, :update_time)
                ");
                $stmt->execute([
                    ':card_id' => $item['card_id'],
                    ':possession_flg' => $item['possession'],
                    ':update_time' => date('Y-m-d H:i:s')
                ]);
            }
        } catch (Exception $e) {
            // エラーが発生した場合の処理
            echo json_encode(['error' => 'Failed processing card_id: ' . $item['card_id'] . '. ' . $e->getMessage()]);
            // このアイテムでエラーが発生したため、他のアイテムの処理を継続する
            continue;
        }
    }

    // コミット
    $conn->commit();

    echo json_encode(['status' => 'success']);
} catch (Exception $e) {
    // ロールバック
    $conn->rollBack();
    echo json_encode(['error' => $e->getMessage()]);
}

// データベース接続を閉じる
$conn = null;
?>