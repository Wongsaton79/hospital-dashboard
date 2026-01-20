<?php
// บรรทัดแรกต้องเป็น <?php เท่านั้น ห้ามมีช่องว่างก่อนหน้านี้เด็ดขาด
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 1. ตั้งค่า Header
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=UTF-8");

// กรณี Browser ถาม Preflight Check
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    // 2. ข้อมูลการเชื่อมต่อ (ตามที่คุณแจ้งมาล่าสุด)
    $servername = "172.16.90.191";
    $username = "wongsaton";
    $password = "mud79comsci";
    $dbname = "bandan"; 
    $port = 3306; 

    // 3. โหมดทดสอบการเชื่อมต่อ (?mode=test)
    if (isset($_GET['mode']) && $_GET['mode'] === 'test') {
        // ใช้ @ เพื่อซ่อน Warning ดิบๆ แล้วจับ Exception แทน
        $conn = @new mysqli($servername, $username, $password, $dbname, $port);
        
        if ($conn->connect_error) {
            $errCode = $conn->connect_errno;
            $errMsg = $conn->connect_error;
            $suggestion = "";
            
            if ($errCode == 2002) $suggestion = " (Server ไม่ตอบสนอง - เช็ค IP หรือ Firewall)";
            if ($errCode == 1045) $suggestion = " (User/Pass ผิด)";
            if ($errCode == 1049) $suggestion = " (ชื่อ Database ผิด)";

            throw new Exception("Connect Error ($errCode): $errMsg $suggestion");
        }
        
        echo json_encode([
            "status" => "success", 
            "message" => "เชื่อมต่อ Database '$dbname' สำเร็จ!",
            "info" => "Host: $servername:$port"
        ]);
        $conn->close();
        exit();
    }

    // 4. โหมดดึงข้อมูลจริง
    $conn = new mysqli($servername, $username, $password, $dbname, $port);

    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }

    $conn->set_charset("utf8");

    // รับค่าวันที่
    $startDate = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d');
    $endDate = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

    // ใช้ SQL Query อย่างง่ายเพื่อทดสอบการดึงข้อมูลก่อน (ถ้าผ่านแล้วค่อยใส่ SQL ยาวๆ ของคุณลงไป)
    $sql = "
    select o.vstdate, o.vsttime, o.vn, p.cid, 
        concat(p.pname,p.fname,' ',p.lname) as ptname,
        t.name as pttype_name, vpt.auth_code
    from ovst o  
    left outer join vn_stat v on v.vn = o.vn  
    left outer join patient p on p.hn = o.hn  
    left outer join pttype t on t.pttype = o.pttype  
    left outer join visit_pttype vpt on vpt.vn = o.vn and vpt.pttype = o.pttype  
    where o.vstdate between '$startDate' and '$endDate'    
    and (o.anonymous_visit is null or o.anonymous_visit = 'N')  
    order by o.vn DESC
    limit 100
    ";

    $result = $conn->query($sql);
    
    if (!$result) {
        throw new Exception("SQL Error: " . $conn->error);
    }

    $data = array();
    while($row = $result->fetch_assoc()) {
        $data[] = $row;
    }

    echo json_encode($data);
    $conn->close();

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => true, "message" => $e->getMessage()]);
}
?>
