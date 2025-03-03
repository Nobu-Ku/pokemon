<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header("Content-Type: application/json; charset=UTF-8");
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// 設定ファイルの読み込み
$config = require 'config.php';

// 設定を変数に格納
$servername = $config['servername'];
$username = $config['username'];
$password = $config['password'];
$dbname = $config['dbname'];

try {
    // データベース接続の作成
    $conn = new PDO("mysql:host=$servername;dbname=$dbname;charset=utf8", $username, $password);
    // エラーモードを例外モードに設定
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // **POSTデータを取得**
    $inputJSON = file_get_contents("php://input");
    $input = json_decode($inputJSON, true);
    
    // display_pack の値を取得（指定がない場合は NULL）
    $displayPack = isset($input['display_pack']) ? (int)$input['display_pack'] : null;

    // SQLクエリ（display_pack の指定がある場合とない場合で分岐）
    if ($displayPack !== null) {
        $stmt = $conn->prepare("
            SELECT c.ID, c.name, c.number, c.rare, c.pack, c.type, c.zukan, c.image, c.display_pack, c.display_number,
                COALESCE(p.possession_flg, false) AS possession_flg
            FROM cards c
            LEFT JOIN possessions p ON c.ID = p.card_id
            WHERE c.display_pack = :display_pack
            ORDER BY c.display_pack DESC, c.display_number ASC
        ");
        $stmt->bindParam(':display_pack', $displayPack, PDO::PARAM_INT);
    } else {
        $stmt = $conn->prepare("
            SELECT c.ID, c.name, c.number, c.rare, c.pack, c.type, c.zukan, c.image, c.display_pack, c.display_number,
                COALESCE(p.possession_flg, false) AS possession_flg
            FROM cards c
            LEFT JOIN possessions p ON c.ID = p.card_id
            ORDER BY c.display_pack DESC, c.display_number ASC
        ");
    }
    $stmt->execute();
    $cards = $stmt->fetchAll(PDO::FETCH_ASSOC);
    // Boolean変換
    foreach ($cards as &$card) {
        $card['possession_flg'] = (bool) $card['possession_flg'];
    }
    unset($card);
    // JSON形式に変換して出力
    echo json_encode($cards);
} catch(PDOException $e) {
    echo json_encode(["error" => $e->getMessage()]);
}
// データベース接続を閉じる
$conn = null;
?>