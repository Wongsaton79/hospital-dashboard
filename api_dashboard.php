<?php
// แสดง Error PHP ทั้งหมดเพื่อช่วย Debug
error_reporting(E_ALL);
ini_set('display_errors', 1);

// --- 1. แก้ไขเรื่อง CORS และ Header ให้ครบถ้วน ---
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=UTF-8");

// กรณี Browser ส่ง OPTIONS มาถามก่อน (Preflight) ให้ตอบกลับทันที
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    // --- 2. ตั้งค่าการเชื่อมต่อ Database (อัปเดตข้อมูลใหม่) ---
    $servername = "172.16.90.191";
    $username = "wongsaton";
    $password = "mud79comsci";
    $dbname = "bandan"; // ชื่อ Database ที่ถูกต้อง
    $port = 3306;       // ระบุ Port 3306 ตามที่แจ้ง

    // --- 3. ตรวจสอบโหมดทดสอบ (Test Connection Mode) ---
    // ถ้ามีการส่งค่า ?mode=test มา เราจะแค่ลอง Connect แล้วจบเลย
    if (isset($_GET['mode']) && $_GET['mode'] === 'test') {
        // เพิ่ม Port เข้าไปในการเชื่อมต่อ
        $conn = @new mysqli($servername, $username, $password, $dbname, $port);
        
        if ($conn->connect_error) {
            $errCode = $conn->connect_errno;
            $errMsg = $conn->connect_error;
            
            // วิเคราะห์สาเหตุเบื้องต้นจาก Error Code
            $suggestion = "";
            if ($errCode == 2002) {
                $suggestion = " (หา Server ไม่เจอ หรือ Port ถูกบล็อก - ลองเช็ค IP และ Firewall)";
            } elseif ($errCode == 1045) {
                $suggestion = " (Username หรือ Password ไม่ถูกต้อง)";
            } elseif ($errCode == 1049) {
                $suggestion = " (ไม่พบฐานข้อมูลชื่อ '$dbname' - เช็คชื่อ DB อีกครั้ง)";
            }

            throw new Exception("เชื่อมต่อ Database ไม่สำเร็จ (Code: $errCode): " . $errMsg . $suggestion);
        }
        
        echo json_encode([
            "status" => "success", 
            "message" => "เชื่อมต่อ Database '$dbname' สำเร็จ!",
            "info" => "Host: $servername:$port"
        ]);
        $conn->close();
        exit(); // จบการทำงานทันที
    }

    // --- 4. โหมดปกติ (ดึงข้อมูล Dashboard) ---
    // เพิ่ม Port เข้าไปในการเชื่อมต่อ
    $conn = new mysqli($servername, $username, $password, $dbname, $port);

    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }

    $conn->set_charset("utf8");

    // รับค่าวันที่
    $startDate = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d');
    $endDate = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

    // SQL Query
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
    // ส่ง Error กลับไปให้หน้าเว็บแสดงผล
    http_response_code(500);
    echo json_encode([
        "error" => true,
        "message" => $e->getMessage()
    ]);
}
?>