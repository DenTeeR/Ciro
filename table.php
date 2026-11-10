<?php
require_once 'function.php';
require_once 'config.php';
require_once 'botapi.php';
global $pdo;
//-----------------------------------------------------------------
try {

    $tableName = 'user';
    $stmt = $pdo->prepare("SELECT 1 FROM information_schema.tables WHERE table_name = :tableName");
    $stmt->bindParam(':tableName', $tableName);
    $stmt->execute();
    $tableExists = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$tableExists) {
        $stmt = $pdo->prepare("CREATE TABLE $tableName (
            id VARCHAR(500) PRIMARY KEY,
            limit_usertest INT(100) NOT NULL,
            roll_Status BOOL NOT NULL,
            username VARCHAR(500) NOT NULL,
            Processing_value TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
            Processing_value_one TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
            Processing_value_tow TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
            Processing_value_four TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
            step VARCHAR(500) NOT NULL,
            description_blocking TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
            number VARCHAR(300) NOT NULL,
            Balance DECIMAL(20,2) NOT NULL DEFAULT 0,
            User_Status VARCHAR(500) NOT NULL,
            pagenumber INT(10) NOT NULL,
            message_count VARCHAR(100) NOT NULL,
            last_message_time VARCHAR(100) NOT NULL,
            agent VARCHAR(100) NOT NULL,
            affiliatescount VARCHAR(100) NOT NULL,
            affiliates VARCHAR(100) NOT NULL,
            namecustom VARCHAR(300) NOT NULL,
            number_username VARCHAR(300) NOT NULL,
            register VARCHAR(100) NOT NULL,
            verify VARCHAR(100) NOT NULL,
            cardpayment VARCHAR(100) NOT NULL,
            codeInvitation VARCHAR(100) NULL,
            pricediscount VARCHAR(100) NULL   DEFAULT '0',
            hide_mini_app_instruction VARCHAR(20) NULL   DEFAULT '0',
            maxbuyagent VARCHAR(100) NULL   DEFAULT '0',
            joinchannel VARCHAR(100) NULL   DEFAULT '0',
            checkstatus VARCHAR(50) NULL   DEFAULT '0',
            bottype TEXT NULL ,
            score INT(255) NULL DEFAULT '0',
            limitchangeloc VARCHAR(50) NULL   DEFAULT '0',
            status_cron VARCHAR(20)  NULL DEFAULT '1',
            expire VARCHAR(100) NULL ,
            token VARCHAR(100) NULL,
            lang VARCHAR(5) NULL  DEFAULT 'fa'
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci");
        $stmt->execute();
    } else {
        addFieldToTable($tableName, "lang", "fa", "varchar(5)");
        addFieldToTable($tableName, "lang_prompted", "0", "varchar(2)");
        addFieldToTable($tableName, 'token', null, "VARCHAR(100)");
        addFieldToTable($tableName, 'status_cron', "1", "VARCHAR(20)");
        addFieldToTable($tableName, 'expire', NULL, "VARCHAR(100)");
        addFieldToTable($tableName, 'limitchangeloc', '0', "VARCHAR(50)");
        addFieldToTable($tableName, 'bottype', '0', "TEXT");
        addFieldToTable($tableName, 'score', '0', "INT(255)");
        addFieldToTable($tableName, 'checkstatus', '0', "VARCHAR(50)");
        addFieldToTable($tableName, 'joinchannel', '0', "VARCHAR(100)");
        addFieldToTable($tableName, 'maxbuyagent', '0');
        addFieldToTable($tableName, 'agent', 'f');
        addFieldToTable($tableName, 'verify', '1');
        addFieldToTable($tableName, 'register', 'none');
        addFieldToTable($tableName, 'namecustom', 'none');
        addFieldToTable($tableName, 'number_username', '100');
        addFieldToTable($tableName, 'cardpayment', '1');
        addFieldToTable($tableName, 'affiliatescount', '0');
        addFieldToTable($tableName, 'affiliates', '0');
        addFieldToTable($tableName, 'message_count', '0');
        addFieldToTable($tableName, 'last_message_time', '0');
        addFieldToTable($tableName, 'Processing_value_four', '');
        addFieldToTable($tableName, 'renew_resume_ctx', '');
        // 💳 a purchase paid directly (directpay.php) lives on the invoice below
        // 💰 what a customer lacks for a pending purchase (shortfall.php)
        addFieldToTable($tableName, 'short_ctx', null, 'TEXT NULL');
        addFieldToTable($tableName, 'username', 'none');
        addFieldToTable($tableName, 'Processing_value', 'none');
        addFieldToTable($tableName, 'number', 'none');
        addFieldToTable($tableName, 'pagenumber', '');
        addFieldToTable($tableName, 'codeInvitation', null);
        addFieldToTable($tableName, 'pricediscount', "0");
        addFieldToTable($tableName, 'hide_mini_app_instruction', '0', "VARCHAR(20)");
    }
} catch (PDOException $e) {
    file_put_contents('error_log user', $e->getMessage());
}

//-----------------------------------------------------------------
try {

    $tableName = 'help';
    $stmt = $pdo->prepare("SELECT 1 FROM information_schema.tables WHERE table_name = :tableName");
    $stmt->bindParam(':tableName', $tableName);
    $stmt->execute();
    $tableExists = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$tableExists) {
        $stmt = $pdo->prepare("CREATE TABLE $tableName (
        id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        name_os varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
        Media_os varchar(5000) NOT NULL,
        type_Media_os varchar(500) NOT NULL,
        category TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
        Description_os TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
        translations TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
        entities_os TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL)
        ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci");
        $stmt->execute();
    }

    // Columns added after the CREATE TABLE above was written. They run whether
    // the table was just made or already existed: a fresh install takes the
    // CREATE arm, and while these sat in an else they were never added to it.
    // addFieldToTable returns at once when the column is there, so an update
    // pays nothing for this.
    addFieldToTable("help", "category", null, "TEXT");
    addFieldToTable("help", "translations", null, "TEXT");
    addFieldToTable("help", "entities_os", null, "TEXT");

} catch (Exception $e) {
    file_put_contents('error_log', $e->getMessage());
}
//-----------------------------------------------------------------
try {

    $tableName = 'setting';
    $stmt = $pdo->prepare("SELECT 1 FROM information_schema.tables WHERE table_name = :tableName");
    $stmt->bindParam(':tableName', $tableName);
    $stmt->execute();
    $DATAAWARD = json_encode(array(
        'one' => "0",
        "tow" => "0",
        "theree" => "0"
    ));
    $limitlist = json_encode(array(
        'free' => 100,
        'all' => 100,
    ));
    $status_cron = json_encode(array(
        'day' => true,
        'volume' => true,
        'remove' => false,
        'remove_volume' => false,
        'test' => false,
        'on_hold' => false,
        'uptime_node' => false,
        'uptime_panel' => false,
    ));
    // A fresh install starts with six buttons on - buy, test account, my
    // services, account, top-up and help. Everything else, language selection
    // included, ships hidden and is turned on from
    // 🎨 شخصی‌سازی ← 🔘 تنظیمات دکمه‌های منوی اصلی when the shop wants it.
    // The row layout still lists them all, so turning one on is a single tap
    // and it lands back in its usual place.
    $keyboardmain = '{"keyboard":[[{"text":"text_sell"},{"text":"text_extend","hidden":true}],[{"text":"text_usertest"},{"text":"text_wheel_luck","hidden":true}],[{"text":"text_Purchased_services"},{"text":"accountwallet"}],[{"text":"addbalance"}],[{"text":"text_affiliates","hidden":true},{"text":"text_Tariff_list","hidden":true}],[{"text":"text_support","hidden":true},{"text":"text_help"}],[{"text":"text_change_language","hidden":true}]]}';
    $tableExists = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$tableExists) {
        $stmt = $pdo->prepare("CREATE TABLE $tableName (
        Bot_Status varchar(200)  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci  NULL,
        roll_Status varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci  NULL,
        get_number varchar(200)  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci  NULL,
        iran_number varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci  NULL,
        NotUser varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci  NULL,
        Channel_Report varchar(600)  NULL,
        limit_usertest_all varchar(600)  NULL,
        affiliatesstatus varchar(600)  NULL,
        affiliatespercentage varchar(600)  NULL,
        removedayc varchar(600)  NULL,
        showcard varchar(200)  NULL,
        numbercount varchar(600)  NULL,
        statusnewuser varchar(600)  NULL,
        statusagentrequest varchar(600)  NULL,
        statuscategory varchar(200)  NULL,
        statusterffh varchar(200)  NULL,
        volumewarn varchar(200)  NULL,
        inlinebtnmain varchar(200)  NULL,
        verifystart varchar(200)  NULL,
        id_support varchar(200)  NULL,
        statusnamecustom varchar(100)  NULL,
        statuscategorygenral varchar(100)  NULL,
        statussupportpv varchar(100)  NULL,
        agentreqprice varchar(100)  NULL,
        bulkbuy varchar(100)  NULL,
        on_hold_day varchar(100)  NULL,
        cronvolumere varchar(100)  NULL,
        verifybucodeuser varchar(100)  NULL,
        scorestatus varchar(100)  NULL,
        Lottery_prize TEXT  NULL,
        wheelـluck varchar(45)  NULL,
        wheelـluck_price varchar(45)  NULL,
        btn_status_extned varchar(45)  NULL,
        daywarn varchar(45)  NULL,
        categoryhelp varchar(45)  NULL,
        linkappstatus varchar(45)  NULL,
        wheelagent varchar(45)  NULL,
        Lotteryagent varchar(45)  NULL,
        statusfirstwheel varchar(45)  NULL,
        statuslimitchangeloc varchar(45)  NULL,
        Debtsettlement varchar(45)  NULL,
        Dice varchar(45) NULL,
        keyboardmain TEXT NOT NULL,
        statusnoteforf varchar(45) NOT NULL,
        statuscopycart varchar(45) NOT NULL,
        timeauto_not_verify varchar(20) NOT NULL,
        status_keyboard_config varchar(20)  NULL,
        cron_status TEXT NOT NULL,
        text_edit JSON NULL,
        limitnumber varchar(200)  NULL)
        ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci");
        $stmt->execute();
        $stmt = $pdo->prepare("INSERT INTO setting (
Bot_Status,roll_Status,get_number,limit_usertest_all,iran_number,NotUser,
affiliatesstatus,affiliatespercentage,removedayc,showcard,statuscategory,
numbercount,statusnewuser,statusagentrequest,volumewarn,inlinebtnmain,
verifystart,statussupportpv,statusnamecustom,statuscategorygenral,
agentreqprice,cronvolumere,bulkbuy,on_hold_day,verifybucodeuser,
scorestatus,Lottery_prize,wheelـluck,wheelـluck_price,daywarn,
categoryhelp,linkappstatus,wheelagent,
Lotteryagent,statusfirstwheel,statuslimitchangeloc,limitnumber,
Debtsettlement,Dice,keyboardmain,statusnoteforf,statuscopycart,
timeauto_not_verify,status_keyboard_config,cron_status
) VALUES (
'botstatuson','rolleoff','offAuthenticationphone','1','offAuthenticationiran','offnotuser',
'offaffiliates','0','0','1','offcategory',
'0','onnewuser','offrequestagent','2','offinline',
'offverify','offpvsupport','offnamecustom','offcategorys',
'0','5','offbulk','4','offverify',
'0','$DATAAWARD','0','0','2',
'0','0','0',
'0','0','0','$limitlist',
'0','0','$keyboardmain','0','1',
'1','0','$status_cron'
)");
        $stmt->execute();
    }

    // Same story as the help table: everything below was added after the CREATE
    // TABLE above was written, and sat in an else, so a fresh install never got
    // any of it - 25 columns missing, which is why a brand new shop came up with
    // no backup schedule, no mini app and no language switch. Unconditional now;
    // addFieldToTable skips a column that already exists.
        addFieldToTable("setting", "cron_status", $status_cron, "TEXT");
        addFieldToTable("setting", "text_edit", "{}", "JSON");
        addFieldToTable("setting", "button_edit", null, "TEXT");
        addFieldToTable("setting", "configColOrder", null, "VARCHAR(20)");
        addFieldToTable("setting", "configColOrderBuy", null, "VARCHAR(20)");
        addFieldToTable("setting", "cardRandomAmount", null, "VARCHAR(20)");
        addFieldToTable("setting", "smsForwardEnabled", null, "VARCHAR(20)");
        addFieldToTable("setting", "smsForwardSecret", null, "VARCHAR(64)");
        addFieldToTable("setting", "backup_db_password", null, "VARCHAR(255)");
        addFieldToTable("setting", "backup_bot_password", null, "VARCHAR(255)");
        addFieldToTable("setting", "backup_db_enabled", "1", "VARCHAR(1)");
        addFieldToTable("setting", "backup_bot_enabled", "1", "VARCHAR(1)");
        addFieldToTable("setting", "backup_interval_hours", "5", "VARCHAR(2)");
        addFieldToTable("setting", "topup_disc_admin_selftest", "0", "VARCHAR(1)");
        // 🛡 دسترسی ادمین: admins keep unlimited test accounts (today's behaviour)
        // and pay for purchases until free purchases are switched on
        addFieldToTable("setting", "admin_test_unlimited", "1", "VARCHAR(1)");
        addFieldToTable("setting", "admin_buy_free", "0", "VARCHAR(1)");
        // ...and earn 🎁's free config by inviting, like everyone, until it is on
        addFieldToTable("setting", "admin_affrw_unlimited", "0", "VARCHAR(1)");
        // «🤖 پنل تحت وب: روشن ✅ / خاموش ❌» - on, as every bot's panel has been
        addFieldToTable("setting", "web_panel_status", "on", "VARCHAR(10)");
        addFieldToTable("setting", "configGetBtnStyle", null, "VARCHAR(20)");
        // the automatic language picker is off on a fresh install - it used to
        // rely on this column being NULL and the reader defaulting to '0', which
        // meant the shipped default was invisible in the database
        addFieldToTable("setting", "lang_switch", '{"enabled":"0","mode":"once","langs":["fa","en"],"blockOthers":"0"}', "TEXT");
        addFieldToTable("setting", "help_layout", null, "TEXT");
        addFieldToTable("setting", "status_keyboard_config", "1", "varchar(20)");
        addFieldToTable("setting", "configDeliveryMode", null, "TEXT");
        addFieldToTable("setting", "statusnoteforf", "1", "varchar(20)");
        addFieldToTable("setting", "timeauto_not_verify", "4", "varchar(20)");
        addFieldToTable("setting", "statuscopycart", "1", "varchar(20)");
        addFieldToTable("setting", "miniapp_status", "offminiapp", "VARCHAR(20)");
        addFieldToTable("setting", "miniapp_bot_logo", "0", "VARCHAR(10)");
        addFieldToTable("setting", "miniapp_bot_name", "", "VARCHAR(60)");
        addFieldToTable("setting", "keyboardmain", $keyboardmain, "TEXT");
        // Every language but Persian keeps its own main menu here; Persian
        // stays in keyboardmain above, so an existing bot's menu carries over
        // untouched and only the other languages start from the factory one.
        addFieldToTable("setting", "keyboardmain_lang", '{}', "TEXT");
        // per-language on/off for the message items that have a switch; an absent
        // value means that item's shipped default (see bt_item_switch_defaults)
        addFieldToTable("setting", "bt_item_lang", '{}', "TEXT");
        addFieldToTable("setting", "Dice", '0', "varchar(45)");
        addFieldToTable("setting", "Debtsettlement", '1', "varchar(45)");
        addFieldToTable("setting", "limitnumber", $limitlist, "varchar(200)");
        addFieldToTable("setting", "statuslimitchangeloc", "0", "varchar(45)");
        addFieldToTable("setting", "statusfirstwheel", "0", "varchar(45)");
        addFieldToTable("setting", "Lotteryagent", "1", "varchar(45)");
        addFieldToTable("setting", "wheelagent", "1", "varchar(45)");
        addFieldToTable("setting", "linkappstatus", "0", "varchar(45)");
        addFieldToTable("setting", "categoryhelp", "0", "varchar(45)");
        // names of tutorial categories, so one can exist before (and after) any
        // tutorial uses it - the rows' own category column only knows the ones
        // currently in use
        addFieldToTable("setting", "help_categories", "[]", "TEXT");
        // one switch for the whole education section, on by default
        addFieldToTable("setting", "help_status", "onhelp", "VARCHAR(20)");
        addFieldToTable("setting", "daywarn", "2", "varchar(45)");
        addFieldToTable("setting", "btn_status_extned", "0", "varchar(45)");
        addFieldToTable("setting", "wheelـluck_price", "0", "varchar(45)");
        addFieldToTable("setting", "wheelـluck", "0", "varchar(45)");
        addFieldToTable("setting", "Lottery_prize", $DATAAWARD, "TEXT");
        addFieldToTable("setting", "scorestatus", "0", "VARCHAR(100)");
        addFieldToTable("setting", "verifybucodeuser", "offverify", "VARCHAR(100)");
        addFieldToTable("setting", "on_hold_day", "4", "VARCHAR(100)");
        addFieldToTable("setting", "bulkbuy", "offbulk", "VARCHAR(100)");
        addFieldToTable("setting", "statuscategorygenral", "oncategorys", "VARCHAR(100)");
    // default ON: keeps today's live behaviour (panel picker always shown,
    // hardcoded via `false &&` in index.php) unchanged unless the admin flips it
    addFieldToTable("setting", "statuspanelshow", "onpanelshow", "VARCHAR(20)");
        addFieldToTable("setting", "cronvolumere", "5", "VARCHAR(100)");
        addFieldToTable("setting", "agentreqprice", "0", "VARCHAR(100)");
        addFieldToTable("setting", "statusnamecustom", "offnamecustom", "VARCHAR(100)");
        addFieldToTable("setting", "id_support", "0", "VARCHAR(100)");
        addFieldToTable("setting", "statussupportpv", "offpvsupport", "VARCHAR(100)");
        addFieldToTable("setting", "affiliatespercentage", "0", "VARCHAR(600)");
        addFieldToTable("setting", "inlinebtnmain", "offinline", "VARCHAR(200)");
        addFieldToTable("setting", "volumewarn", "2", "VARCHAR(200)");
        addFieldToTable("setting", "statusagentrequest", "offrequestagent", "VARCHAR(600)");
        addFieldToTable("setting", "statusnewuser", "onnewuser", "VARCHAR(600)");
        addFieldToTable("setting", "numbercount", "0", "VARCHAR(600)");
        addFieldToTable("setting", "statuscategory", "offcategory", "VARCHAR(600)");
        addFieldToTable("setting", "showcard", "1", "VARCHAR(200)");
        addFieldToTable("setting", "removedayc", "1", "VARCHAR(200)");
        addFieldToTable("setting", "affiliatesstatus", "offaffiliates", "VARCHAR(600)");
        addFieldToTable("setting", "NotUser", "offnotuser", "VARCHAR(200)");
        addFieldToTable("setting", "iran_number", "offAuthenticationiran", "VARCHAR(200)");
        addFieldToTable("setting", "get_number", "onAuthenticationphone", "VARCHAR(200)");
        addFieldToTable("setting", "limit_usertest_all", "1", "VARCHAR(200)");
    addFieldToTable("setting", "usertest_auto_reset", "{}", "JSON");
        addFieldToTable("setting", "Channel_Report", "0", "VARCHAR(200)");
        addFieldToTable("setting", "Bot_Status", "botstatuson", "VARCHAR(200)");
        addFieldToTable("setting", "roll_Status", "rolleoff", "VARCHAR(200)");
        addFieldToTable("setting", "verifystart", "offverify", "VARCHAR(200)");
} catch (Exception $e) {
    file_put_contents('error_log', $e->getMessage());
}

//-----------------------------------------------------------------
try {
    $tableName = 'admin';
    $stmt = $pdo->prepare("SELECT 1 FROM information_schema.tables WHERE table_name = :tableName");
    $stmt->bindParam(':tableName', $tableName);
    $stmt->execute();
    $tableExists = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$tableExists) {
        $stmt = $pdo->prepare("CREATE TABLE $tableName (
        id_admin varchar(500) PRIMARY KEY NOT NULL,
        username varchar(1000) NOT NULL,
        password varchar(1000) NOT NULL,
        rule varchar(500) NOT NULL)");
        $stmt->execute();
        $randomString = bin2hex(random_bytes(5));
        $stmt = $pdo->prepare("INSERT INTO admin (id_admin,rule,username,password) VALUES (:mp1,'administrator','admin',:mp2)");
        $stmt->execute([':mp1' => $adminnumber, ':mp2' => $randomString]);
        $mz_fresh_install = true; // no admin table yet: a new bot, not an updated one
    } else {
        addFieldToTable("admin", "rule", "administrator", "VARCHAR(200)");
        addFieldToTable("admin", "username", null, "VARCHAR(200)");
        addFieldToTable("admin", "password", null, "VARCHAR(200)");
    }
} catch (Exception $e) {
    file_put_contents('error_log admin', $e->getMessage());
}
//-----------------------------------------------------------------
try {
    $tableName = 'channels';
    $stmt = $pdo->prepare("SELECT 1 FROM information_schema.tables WHERE table_name = :tableName");
    $stmt->bindParam(':tableName', $tableName);
    $stmt->execute();
    $tableExists = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$tableExists) {
        $stmt = $pdo->prepare("CREATE TABLE $tableName (
            remark varchar(200) NOT NULL,
            linkjoin varchar(200) NOT NULL,
            link varchar(200) NOT NULL)");
        $stmt->execute();
    } else {
        addFieldToTable("channels", "remark", null, "VARCHAR(200)");
        addFieldToTable("channels", "linkjoin", null, "VARCHAR(200)");
    }
} catch (Exception $e) {
    file_put_contents('error_log channels', $e->getMessage());
}
// ui_texts(), not languagechange(): the rows seeded below are looked up again
// from the webhook and the crons, and they resolve the 'Admin' vocabulary the
// same way there - Persian, whatever language the installer reads in.
$textbotlang = ui_texts();

//--------------------------------------------------------------
try {

    $result = $pdo->query("SHOW TABLES LIKE 'marzban_panel'");
    $table_exists = ($result->rowCount() > 0);

    if (!$table_exists) {
        $result = $pdo->query("CREATE TABLE marzban_panel (
        id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        code_panel varchar(200) NULL,
        name_panel varchar(2000) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
        status varchar(500) NULL,
        url_panel varchar(2000) NULL,
        username_panel varchar(200) NULL,
        password_panel TEXT NULL,
        agent varchar(200) NULL,
        sublink varchar(500) NULL,
        config varchar(500) NULL,
        MethodUsername varchar(700) NULL,
        TestAccount varchar(100) NULL,
        limit_panel varchar(100) NULL,
        namecustom varchar(100) NULL,
        Methodextend varchar(100) NULL,
        conecton varchar(100) NULL,
        linksubx varchar(1000) NULL,
        inboundid varchar(100) NULL,
        type varchar(100) NULL,
        inboundstatus varchar(100) NULL,
        inbound_deactive varchar(100) NULL,
        time_usertest varchar(100) NULL,
        val_usertest varchar(100)  NULL,
        secret_code varchar(200) NULL,
        priceChangeloc varchar(200) NULL,
        priceextravolume varchar(500) NULL,
        pricecustomvolume varchar(500) NULL,
        pricecustomtime varchar(500) NULL,
        priceextratime varchar(500) NULL,
        mainvolume varchar(500) NULL,
        maxvolume varchar(500) NULL,
        maintime varchar(500) NULL,
        maxtime varchar(500) NULL,
        status_extend varchar(100) NULL,
        datelogin TEXT NULL,
        proxies TEXT NULL,
        inbounds TEXT NULL,
        subvip varchar(60) NULL,
        changeloc varchar(60) NULL,
        on_hold_test varchar(60) NOT NULL,
        version_panel varchar(60) NOT NULL,
        customvolume TEXT NULL,
        hide_user TEXT NULL)
        ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci");
        if (!$result) {
            echo "table marzban_panel" . implode(' ', $pdo->errorInfo());
        }
    }
    {
        $VALUE = json_encode(array(
            'f' => '0',
            'n' => '0',
            'n2' => '0'
        ));
        $valueprice = json_encode(array(
            'f' => "4000",
            'n' => "4000",
            'n2' => "4000"
        ));
        $valuemain = json_encode(array(
            'f' => "1",
            'n' => "1",
            'n2' => "1"
        ));
        $valuemax = json_encode(array(
            'f' => "1000",
            'n' => "1000",
            'n2' => "1000"
        ));
        $valuemax_time = json_encode(array(
            'f' => "365",
            'n' => "365",
            'n2' => "365"
        ));
        addFieldToTable("marzban_panel", "version_panel", "0", "VARCHAR(60)");
        addFieldToTable("marzban_panel", "on_hold_test", "1", "VARCHAR(60)");
        addFieldToTable("marzban_panel", "proxies", null, "TEXT");
        addFieldToTable("marzban_panel", "inbounds", null, "TEXT");
        addFieldToTable("marzban_panel", "customvolume", $VALUE, "TEXT");
        addFieldToTable("marzban_panel", "subvip", "offsubvip", "VARCHAR(60)");
        addFieldToTable("marzban_panel", "changeloc", "offchangeloc", "VARCHAR(60)");
        addFieldToTable("marzban_panel", "hide_user", null, "TEXT");
        addFieldToTable("marzban_panel", "status_extend", "on_extend", "VARCHAR(50)");
        addFieldToTable("marzban_panel", "code_panel", null, "VARCHAR(50)");
        addFieldToTable("marzban_panel", "priceextravolume", $valueprice, "VARCHAR(500)");
        addFieldToTable("marzban_panel", "pricecustomvolume", $valueprice, "VARCHAR(500)");
        addFieldToTable("marzban_panel", "pricecustomtime", $valueprice, "VARCHAR(500)");
        addFieldToTable("marzban_panel", "priceextratime", $valueprice, "VARCHAR(500)");
        addFieldToTable("marzban_panel", "priceChangeloc", "0", "VARCHAR(100)");
        addFieldToTable("marzban_panel", "mainvolume", $valuemain, "VARCHAR(500)");
        addFieldToTable("marzban_panel", "maxvolume", $valuemax, "VARCHAR(500)");
        addFieldToTable("marzban_panel", "maintime", $valuemain, "VARCHAR(500)");
        addFieldToTable("marzban_panel", "maxtime", $valuemax_time, "VARCHAR(500)");
        addFieldToTable("marzban_panel", "MethodUsername", $textbotlang['keyboard']['numericIdRandom'], "VARCHAR(100)");
        addFieldToTable("marzban_panel", "datelogin", null, "TEXT");
        addFieldToTable("marzban_panel", "val_usertest", "100", "VARCHAR(50)");
        addFieldToTable("marzban_panel", "time_usertest", "1", "VARCHAR(50)");
        addFieldToTable("marzban_panel", "del_usertest", "0", "VARCHAR(50)");
        // 🚀 a test account's speed in Mbps (Rebecca only; 0 = none)
        addFieldToTable("marzban_panel", "speed_usertest", "0", "VARCHAR(20)");
        addFieldToTable("marzban_panel", "secret_code", null, "VARCHAR(200)");
        addFieldToTable("marzban_panel", "inboundstatus", "offinbounddisable", "VARCHAR(50)");
        addFieldToTable("marzban_panel", "inbound_deactive", "0", "VARCHAR(100)");
        addFieldToTable("marzban_panel", "agent", "all", "VARCHAR(50)");
        addFieldToTable("marzban_panel", "lang", "all", "VARCHAR(20)");
        addFieldToTable("marzban_panel", "inboundid", "1", "VARCHAR(50)");
        addFieldToTable("marzban_panel", "linksubx", null, "VARCHAR(200)");
        addFieldToTable("marzban_panel", "conecton", "offconecton", "VARCHAR(100)");
        addFieldToTable("marzban_panel", "type", "marzban", "VARCHAR(50)");
        // the agent panel type was renamed with the project; panels saved
        // under the name it had before (spelled in hex, so the word itself is
        // not in the code) keep working under the new one
        $pdo->prepare("UPDATE marzban_panel SET type = 'ciro_agent' WHERE type = ?")
            ->execute([hex2bin('6d69727a615f6167656e74')]);
        addFieldToTable("marzban_panel", "Methodextend", $textbotlang['keyboard']['resetVolumeTime'], "VARCHAR(100)");
        addFieldToTable("marzban_panel", "namecustom", "vpn", "VARCHAR(100)");
        addFieldToTable("marzban_panel", "limit_panel", "unlimted", "VARCHAR(50)");
        addFieldToTable("marzban_panel", "TestAccount", "ONTestAccount", "VARCHAR(50)");
        addFieldToTable("marzban_panel", "status", "active", "VARCHAR(50)");
        addFieldToTable("marzban_panel", "sublink", "onsublink", "VARCHAR(50)");
        addFieldToTable("marzban_panel", "config", "offconfig", "VARCHAR(50)");
        addFieldToTable("marzban_panel", "version_panel", "0", "VARCHAR(60)");
        $max_stmt = $pdo->prepare("SELECT MAX(CAST(SUBSTRING(code_panel, 3) AS UNSIGNED)) as max_num FROM marzban_panel WHERE code_panel LIKE '7e%'");
        $max_stmt->execute();
        $max_row = $max_stmt->fetch(PDO::FETCH_ASSOC);
        $next_num = $max_row['max_num'] ? (int) $max_row['max_num'] + 1 : 15;
        $stmt = $pdo->prepare("SELECT id FROM marzban_panel WHERE code_panel IS NULL OR code_panel = ''");
        $stmt->execute();
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $code = '7e' . $next_num;
            $stmt = $pdo->prepare("UPDATE marzban_panel SET code_panel = ? WHERE id = ?");
            $stmt->execute([$code, $row['id']]);
            $next_num++;
        }
    }
} catch (Exception $e) {
    file_put_contents('error_log', $e->getMessage());
}

//-----------------------------------------------------------------
try {

    $result = $pdo->query("SHOW TABLES LIKE 'product'");
    $table_exists = ($result->rowCount() > 0);

    if (!$table_exists) {
        $result = $pdo->query("CREATE TABLE product (
        id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        code_product varchar(200)  NULL,
        name_product varchar(2000) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
        price_product varchar(2000) NULL,
        Volume_constraint varchar(2000) NULL,
        Location varchar(200) NULL,
        Service_time varchar(200) NULL,
        agent varchar(100) NULL,
        note TEXT NULL,
        data_limit_reset varchar(200) NULL,
        one_buy_status varchar(20) NOT NULL,
        inbounds TEXT NULL,
        proxies TEXT NULL,
        category varchar(400) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
        hide_panel TEXT  NOT NULL)
        ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci");
        if (!$result) {
            echo "table product" . implode(' ', $pdo->errorInfo());
        }
    }
    {
        addFieldToTable("product", "one_buy_status", "0", "VARCHAR(20)");
        addFieldToTable("product", "Location", null, "VARCHAR(200)");
        addFieldToTable("product", "inbounds", null, "TEXT");
        addFieldToTable("product", "proxies", null, "TEXT");
        addFieldToTable("product", "category", null, "varchar(200)");
        addFieldToTable("product", "note", '', "TEXT");
        addFieldToTable("product", "hide_panel", '{}', "TEXT");
        addFieldToTable("product", "data_limit_reset", "no_reset", "varchar(100)");
        addFieldToTable("product", "agent", "f", "varchar(50)");
        addFieldToTable("product", "lang", "all", "varchar(20)");
        addFieldToTable("product", "code_product", null, "varchar(50)");
        // 🚀 the speed its accounts get, in Mbps (Rebecca only; 0 = none)
        addFieldToTable("product", "speed_limit", "0", "VARCHAR(20)");
    }
} catch (Exception $e) {
    file_put_contents('error_log', $e->getMessage());
}
//-----------------------------------------------------------------
try {

    $result = $pdo->query("SHOW TABLES LIKE 'invoice'");
    $table_exists = ($result->rowCount() > 0);

    if (!$table_exists) {
        $result = $pdo->query("CREATE TABLE invoice (
        id_invoice varchar(200) PRIMARY KEY,
        id_user varchar(200) NULL,
        username varchar(300) NULL,
        Service_location varchar(300) NULL,
        time_sell VARCHAR(200) NULL,
        name_product varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
        price_product varchar(200) NULL,
        Volume varchar(200) NULL,
        Service_time varchar(200) NULL,
        uuid TEXT NULL,
        note varchar(500) NULL,
        user_info TEXT NULL,
        bottype varchar(200) NULL,
        refral varchar(100) NULL,
        time_cron varchar(100) NULL,
        notifctions TEXT NOT NULL,
        Status varchar(200) NULL)
        ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci");
        if (!$result) {
            echo "table invoice" . implode(' ', $pdo->errorInfo());
        }
    } else {
        $Check_filde = $pdo->query("SHOW COLUMNS FROM invoice LIKE 'note'");
        if (($Check_filde)->rowCount() != 1) {
            $result = $pdo->query("ALTER TABLE invoice ADD note VARCHAR(700)");
        }
        $Check_filde = $pdo->query("SHOW COLUMNS FROM invoice LIKE 'notifctions'");
        if (($Check_filde)->rowCount() != 1) {
            $data = json_encode(array(
                'volume' => false,
                'time' => false,
            ));
            $result = $pdo->query("ALTER TABLE invoice ADD notifctions TEXT NOT NULL");
            $__q1 = $pdo->prepare("UPDATE invoice SET notifctions = ?");
            $__q1->bindValue(1, $data, PDO::PARAM_STR);
            $__q1->execute();
        }
        $Check_filde = $pdo->query("SHOW COLUMNS FROM invoice LIKE 'time_cron'");
        if (($Check_filde)->rowCount() != 1) {
            $result = $pdo->query("ALTER TABLE invoice ADD time_cron VARCHAR(100)");
        }
        $Check_filde = $pdo->query("SHOW COLUMNS FROM invoice LIKE 'refral'");
        if (($Check_filde)->rowCount() != 1) {
            $result = $pdo->query("ALTER TABLE invoice ADD refral VARCHAR(100)");
        }
        $Check_filde = $pdo->query("SHOW COLUMNS FROM invoice LIKE 'bottype'");
        if (($Check_filde)->rowCount() != 1) {
            $result = $pdo->query("ALTER TABLE invoice ADD bottype VARCHAR(200)");
        }
        $Check_filde = $pdo->query("SHOW COLUMNS FROM invoice LIKE 'user_info'");
        if (($Check_filde)->rowCount() != 1) {
            $result = $pdo->query("ALTER TABLE invoice ADD user_info TEXT");
        }
        $Check_filde = $pdo->query("SHOW COLUMNS FROM invoice LIKE 'time_sell'");
        if (($Check_filde)->rowCount() != 1) {
            $result = $pdo->query("ALTER TABLE invoice ADD time_sell VARCHAR(200)");
        }
        $Check_filde = $pdo->query("SHOW COLUMNS FROM invoice LIKE 'uuid'");
        if (($Check_filde)->rowCount() != 1) {
            $result = $pdo->query("ALTER TABLE invoice ADD uuid TEXT");
        }
        $Check_filde = $pdo->query("SHOW COLUMNS FROM invoice LIKE 'Status'");
        if (($Check_filde)->rowCount() != 1) {
            $result = $pdo->query("ALTER TABLE invoice ADD Status VARCHAR(100)");
        }
    }
} catch (Exception $e) {
    file_put_contents('error_log', $e->getMessage());
}
//-----------------------------------------------------------------
try {

    $result = $pdo->query("SHOW TABLES LIKE 'Payment_report'");
    $table_exists = ($result->rowCount() > 0);

    if (!$table_exists) {
        $result = $pdo->query("CREATE TABLE Payment_report (
        id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        id_user varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
        id_order varchar(2000) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
        time varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
        at_updated varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
        price varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
        dec_not_confirmed TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
        Payment_Method varchar(400) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
        payment_Status varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
        bottype varchar(300) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
        message_id INT NULL,
        receipt_msg_id INT NULL,
        disc_promise TEXT NULL,
        id_invoice varchar(1000) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL)
        ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        if (!$result) {
            echo "table Payment_report" . implode(' ', $pdo->errorInfo());
        }
    } else {
        ensureTableUtf8mb4('Payment_report');
        addFieldToTable("Payment_report", "message_id", null, "INT");
        addFieldToTable("Payment_report", "receipt_msg_id", null, "INT");
        // the discount an invoice promised when it was created (topup_disc_promise_save)
        addFieldToTable("Payment_report", "disc_promise", null, "TEXT");
        $Check_filde = $pdo->query("SHOW COLUMNS FROM Payment_report LIKE 'Payment_Method'");
        if (($Check_filde)->rowCount() != 1) {
            $pdo->query("ALTER TABLE Payment_report ADD Payment_Method VARCHAR(200)");
            echo "The Payment_Method field was added ✅";
        }
        $Check_filde = $pdo->query("SHOW COLUMNS FROM Payment_report LIKE 'bottype'");
        if (($Check_filde)->rowCount() != 1) {
            $pdo->query("ALTER TABLE Payment_report ADD bottype VARCHAR(300)");
            echo "The bottype field was added ✅";
        }
        $Check_filde = $pdo->query("SHOW COLUMNS FROM Payment_report LIKE 'at_updated'");
        if (($Check_filde)->rowCount() != 1) {
            $pdo->query("ALTER TABLE Payment_report ADD at_updated VARCHAR(200)");
            echo "The at_updated field was added ✅";
        }
        $Check_filde = $pdo->query("SHOW COLUMNS FROM Payment_report LIKE 'id_invoice'");
        if (($Check_filde)->rowCount() != 1) {
            $pdo->query("ALTER TABLE Payment_report ADD id_invoice VARCHAR(400)");
            $pdo->query("UPDATE Payment_report SET id_invoice = 'none'");
            echo "The id_invoice field was added ✅";
        }
    }
} catch (Exception $e) {
    file_put_contents('error_log', $e->getMessage());
}
//-----------------------------------------------------------------
try {

    $result = $pdo->query("SHOW TABLES LIKE 'Discount'");
    $table_exists = ($result->rowCount() > 0);

    if (!$table_exists) {
        $result = $pdo->query("CREATE TABLE Discount (
        id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        code varchar(2000) NULL,
        price varchar(200) NULL,
        limituse varchar(200) NULL,
        limitused varchar(200) NULL)
        ");
        if (!$result) {
            echo "table Discount" . implode(' ', $pdo->errorInfo());
        }
    } else {
        $Check_filde = $pdo->query("SHOW COLUMNS FROM Discount LIKE 'limituse'");
        if (($Check_filde)->rowCount() != 1) {
            $pdo->query("ALTER TABLE Discount ADD limituse VARCHAR(200)");
            echo "The limituse field was added ✅";
        }
        $Check_filde = $pdo->query("SHOW COLUMNS FROM Discount LIKE 'limitused'");
        if (($Check_filde)->rowCount() != 1) {
            $pdo->query("ALTER TABLE Discount ADD limitused VARCHAR(200)");
            echo "The limitused field was added ✅";
        }
    }
} catch (Exception $e) {
    file_put_contents('error_log', $e->getMessage());
}
//-----------------------------------------------------------------
// top-up discount: which code a user currently has activated (one at a time -
// activating another replaces it) and a log of every successful application
try {
    $result = $pdo->query("SHOW TABLES LIKE 'topup_discount_user'");
    if ($result->rowCount() === 0) {
        $result = $pdo->query("CREATE TABLE topup_discount_user (
        id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        id_user varchar(200) NULL,
        code varchar(200) NULL,
        lang varchar(20) NULL,
        gateway varchar(100) NULL,
        activated_at varchar(50) NULL)");
        if (!$result) {
            echo "table topup_discount_user" . implode(' ', $pdo->errorInfo());
        }
    }
} catch (Exception $e) {
    file_put_contents('error_log', $e->getMessage());
}
try {
    $result = $pdo->query("SHOW TABLES LIKE 'topup_discount_use'");
    if ($result->rowCount() === 0) {
        $result = $pdo->query("CREATE TABLE topup_discount_use (
        id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        id_user varchar(200) NULL,
        code varchar(200) NULL,
        lang varchar(20) NULL,
        gateway varchar(100) NULL,
        paid varchar(100) NULL,
        bonus varchar(100) NULL,
        used_at varchar(50) NULL)");
        if (!$result) {
            echo "table topup_discount_use" . implode(' ', $pdo->errorInfo());
        }
    }
} catch (Exception $e) {
    file_put_contents('error_log', $e->getMessage());
}
//-----------------------------------------------------------------
try {

    $result = $pdo->query("SHOW TABLES LIKE 'Giftcodeconsumed'");
    $table_exists = ($result->rowCount() > 0);

    if (!$table_exists) {
        $result = $pdo->query("CREATE TABLE  Giftcodeconsumed (
        id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        code varchar(2000) NULL,
        id_user varchar(200) NULL)");
        if (!$result) {
            echo "table Giftcodeconsumed" . implode(' ', $pdo->errorInfo());
        }
    }
} catch (Exception $e) {
    file_put_contents('error_log', $e->getMessage());
}
try {
    $result = $pdo->query("SHOW TABLES LIKE 'PaySetting'");
    $table_exists = ($result->rowCount() > 0);
    $main = 20000;
    $max = 1000000;
    $settings = [
        ['Cartstatus', 'offcard'],
        ['CartDirect', '@cart'],
        ['cardnumber', '603700000000'],
        ['namecard', $textbotlang['db_defaults']['namecardNotSet']],
        ['Cartstatuspv', 'offcardpv'],
        ['apinowpayment', '0'],
        ['nowpaymentstatus', 'offnowpayment'],
        ['digistatus', 'offdigi'],
        ['statusSwapWallet', 'offnSolutions'],
        ['statusaqayepardakht', 'offaqayepardakht'],
        ['merchant_id_aqayepardakht', '0'],
        ['minbalance', '20000'],
        ['maxbalance', '1000000'],
        ['marchent_tronseller', '0'],
        ['walletaddress', '0'],
        ['chashbackcart', '0'],
        ['chashbackstar', '0'],
        ['chashbackperfect', '0'],
        ['chashbackaqaypardokht', '0'],
        ['chashbackiranpay1', '0'],
        ['chashbackplisio', '0'],
        ['chashbackzarinpal', '0'],
        ['chashbackfrenzyex', '0'],
        ['checkpaycartfirst', 'offpayverify'],
        ['zarinpalstatus', 'offzarinpal'],
        ['merchant_zarinpal', '0'],
        // درگاه ارزی ریالی FrenzyEx - defaults to OFF (Constitution VII), same
        // shape as zarinpal's own rows just above
        ['frenzyexstatus', 'offfrenzyex'],
        ['frenzyex_api_key', '0'],
        ['frenzyex_callback_secret', '0'],
        // CubePay, AbanGateway and Variza (from the original bot) - off until set up
        ['statuscubepay', 'offcubepay'],
        ['cubepay_api_token', '0'],
        ['cubepay_fee', '0'],
        ['chashbackcubepay', '0'],
        ['statusabangateway', 'offabangateway'],
        ['abangateway_api_key', '0'],
        ['abangateway_endpoint', '0'],
        ['abangateway_dailylimit', '0'],
        ['chashbackabangateway', '0'],
        ['statusvariza', 'offvariza'],
        ['variza_api_token', '0'],
        ['variza_webhook_secret', '0'],
        ['chashbackvariza', '0'],
        // درگاه ارزی ریالی ترونادو (API v5) - off until set up
        ['statustronado', 'offtronado'],
        ['tronado_api_key', '0'],
        ['tronado_wallet', '0'],
        ['tronado_ipn_key', '0'],
        ['tronado_wage_percent', '0'],
        // the smallest invoice Tronado's own page accepts (0 = none)
        ['tronado_min_amount', '150000'],
        ['chashbacktronado', '0'],
        ['tronado_disputes_seen', '[]'],
        // درگاه ارزی ریالی هوش‌پی (HooshPay) - off until set up
        ['statushooshpay', 'offhooshpay'],
        ['hooshpay_api_key', '0'],
        ['hooshpay_secret', '0'],
        ['hooshpay_fee_mode', '0'],
        ['chashbackhooshpay', '0'],
        ['minbalancecart', $main],
        ['maxbalancecart', $max],
        ['minbalancestar', $main],
        ['maxbalancestar', $max],
        ['minbalanceplisio', $main],
        ['maxbalanceplisio', $max],
        ['minbalancedigitaltron', $main],
        ['maxbalancedigitaltron', $max],
        ['minbalanceiranpay1', $main],
        ['maxbalanceiranpay1', $max],
        ['minbalanceaqayepardakht', $main],
        ['maxbalanceaqayepardakht', $max],
        ['minbalancepaynotverify', $main],
        ['maxbalancepaynotverify', $max],
        ['minbalanceperfect', $main],
        ['maxbalanceperfect', $max],
        ['minbalancezarinpal', $main],
        ['maxbalancezarinpal', $max],
        ['minbalancefrenzyex', $main],
        ['maxbalancefrenzyex', $max],
        ['minbalancecubepay', $main],
        ['maxbalancecubepay', $max],
        ['minbalanceabangateway', $main],
        ['maxbalanceabangateway', $max],
        ['minbalancevariza', $main],
        ['maxbalancevariza', $max],
        // Tronado itself caps one payer at 500,000 toman a day, so a larger
        // invoice would only be refused on its own page
        ['minbalancetronado', $main],
        ['maxbalancetronado', 500000],
        ['minbalancehooshpay', $main],
        ['maxbalancehooshpay', $max],
        ['minbalanceiranpay', $main],
        ['maxbalanceiranpay', $max],
        ['minbalancenowpayment', $main],
        ['maxbalancenowpayment', $max],
        ['statusiranpay3', 'offiranpay3'],
        ['apiiranpay', '0'],
        ['chashbackiranpay3', '0'],
        ['helpcart', '2'],
        ['helpaqayepardakht', '2'],
        ['helpstar', '2'],
        ['helpplisio', '2'],
        ['helpiranpay1', '2'],
        ['helpiranpay3', '2'],
        ['helpperfectmony', '2'],
        ['helpzarinpal', '2'],
        ['helpfrenzyex', '2'],
        ['helpcubepay', '2'],
        ['helpabangateway', '2'],
        ['helpvariza', '2'],
        ['helptronado', '2'],
        ['helphooshpay', '2'],
        ['helpnowpayment', '2'],
        ['helpofflinearze', '2'],
        ['autoconfirmcart', 'offauto'],
        ['cashbacknowpayment', '0'],
        ['statusstar', '0'],
        ['statusnowpayment', '0'],
        ['Exception_auto_cart', '{}'],
        ['marchent_floypay', '0'],
    ];
    if (!$table_exists) {
        $result = $pdo->query("CREATE TABLE PaySetting (
        NamePay varchar(500) PRIMARY KEY NOT NULL,
        ValuePay TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL)
        ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci");
        if (!$result) {
            echo "table PaySetting" . implode(' ', $pdo->errorInfo());
        }

        foreach ($settings as $setting) {
            $__q2 = $pdo->prepare("INSERT INTO PaySetting (NamePay, ValuePay) VALUES (?, ?)");
            $__q2->bindValue(1, $setting[0], PDO::PARAM_STR);
            $__q2->bindValue(2, $setting[1], PDO::PARAM_STR);
            $__q2->execute();
        }
    } else {
        foreach ($settings as $setting) {
            $__q3 = $pdo->prepare("INSERT IGNORE INTO PaySetting (NamePay, ValuePay) VALUES (?, ?)");
            $__q3->bindValue(1, $setting[0], PDO::PARAM_STR);
            $__q3->bindValue(2, $setting[1], PDO::PARAM_STR);
            $__q3->execute();
        }





    }
} catch (Exception $e) {
    file_put_contents('error_log', $e->getMessage());
}
// The original bot's own Tronado gateway («درگاه پرداخت ریالی سوم», key
// iranpay2) was removed in favour of the v5 one - its settings go with it.
// Idempotent; its old payments stay in Payment_report as history.
try {
    $pdo->exec("DELETE FROM PaySetting WHERE NamePay IN ('statustarnado', 'apiternado', 'urlpaymenttron', 'chashbackiranpay2', 'minbalanceiranpay2', 'maxbalanceiranpay2', 'helpiranpay2')");
} catch (Exception $e) {
    error_log('legacy tronado cleanup: ' . $e->getMessage());
}
// An update lays the new files over the old ones and deletes nothing, so its
// callback file would stay behind - removed here, but only the old one (it
// reads apiternado), never a file someone else put at that path.
$legacyTronado = __DIR__ . '/payment/tronado.php';
if (is_file($legacyTronado) && strpos((string) @file_get_contents($legacyTronado), 'apiternado') !== false) {
    @unlink($legacyTronado);
}
//----------------------- [ Discount ] --------------------- //
try {
    $result = $pdo->query("SHOW TABLES LIKE 'DiscountSell'");
    $table_exists = ($result->rowCount() > 0);

    if (!$table_exists) {
        $result = $pdo->query("CREATE TABLE DiscountSell (
        id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        codeDiscount varchar(1000)  NOT NULL,
        price varchar(200)  NOT NULL,
        limitDiscount varchar(500)  NOT NULL,
        agent varchar(500)  NOT NULL,
        usefirst varchar(100)  NOT NULL,
        useuser varchar(100)  NOT NULL,
        code_product varchar(100)  NOT NULL,
        code_panel varchar(100)  NOT NULL,
        time varchar(100)  NOT NULL,
        type varchar(100)  NOT NULL,
        usedDiscount varchar(500) NOT NULL)");
        if (!$result) {
            echo "table DiscountSell" . implode(' ', $pdo->errorInfo());
        }
    } else {
        $Check_filde = $pdo->query("SHOW COLUMNS FROM DiscountSell LIKE 'agent'");
        if (($Check_filde)->rowCount() != 1) {
            $pdo->query("ALTER TABLE DiscountSell ADD agent VARCHAR(100)");
            echo "The agent discount field was added ✅";
        }
        $Check_filde = $pdo->query("SHOW COLUMNS FROM DiscountSell LIKE 'type'");
        if (($Check_filde)->rowCount() != 1) {
            $pdo->query("ALTER TABLE DiscountSell ADD type VARCHAR(100)");
            echo "The agent type field was added ✅";
        }
        $Check_filde = $pdo->query("SHOW COLUMNS FROM DiscountSell LIKE 'time'");
        if (($Check_filde)->rowCount() != 1) {
            $pdo->query("ALTER TABLE DiscountSell ADD time VARCHAR(100)");
            echo "The agent time field was added ✅";
        }
        $Check_filde = $pdo->query("SHOW COLUMNS FROM DiscountSell LIKE 'code_panel'");
        if (($Check_filde)->rowCount() != 1) {
            $pdo->query("ALTER TABLE DiscountSell ADD code_panel VARCHAR(100)");
            echo "The code_panel discount field was added ✅";
        }
        $Check_filde = $pdo->query("SHOW COLUMNS FROM DiscountSell LIKE 'code_product'");
        if (($Check_filde)->rowCount() != 1) {
            $pdo->query("ALTER TABLE DiscountSell ADD code_product VARCHAR(100)");
            echo "The code_product discount field was added ✅";
        }
        $Check_filde = $pdo->query("SHOW COLUMNS FROM DiscountSell LIKE 'useuser'");
        if (($Check_filde)->rowCount() != 1) {
            $pdo->query("ALTER TABLE DiscountSell ADD useuser VARCHAR(100)");
            echo "The useuser discount field was added ✅";
        }
        $Check_filde = $pdo->query("SHOW COLUMNS FROM DiscountSell LIKE 'usefirst'");
        if (($Check_filde)->rowCount() != 1) {
            $pdo->query("ALTER TABLE DiscountSell ADD usefirst VARCHAR(100)");
            echo "The usefirst discount field was added ✅";
        }
    }
} catch (Exception $e) {
    file_put_contents('error_log', $e->getMessage());
}
//-----------------------------------------------------------------
try {
    $result = $pdo->query("SHOW TABLES LIKE 'affiliates'");
    $table_exists = ($result->rowCount() > 0);

    if (!$table_exists) {
        $result = $pdo->query("CREATE TABLE affiliates (
        description TEXT  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci  NULL,
        status_commission varchar(200)  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci  NULL,
        Discount varchar(200)  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci  NULL,
        price_Discount varchar(200)  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci  NULL,
        porsant_one_buy varchar(100),
        id_media varchar(300) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci  NULL)
        ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci");
        if (!$result) {
            echo "table affiliates" . implode(' ', $pdo->errorInfo());
        }
        $pdo->query("INSERT INTO affiliates (description,id_media,status_commission,Discount,porsant_one_buy) VALUES ('none','none','oncommission','onDiscountaffiliates','off_buy_porsant')");
    } else {
        $Check_filde = $pdo->query("SHOW COLUMNS FROM affiliates LIKE 'porsant_one_buy'");
        if (($Check_filde)->rowCount() != 1) {
            $pdo->query("ALTER TABLE affiliates ADD porsant_one_buy VARCHAR(100)");
            $pdo->query("UPDATE affiliates SET porsant_one_buy = 'off_buy_porsant'");
            echo "The Discount field was added ✅";
        }
        $Check_filde = $pdo->query("SHOW COLUMNS FROM affiliates LIKE 'Discount'");
        if (($Check_filde)->rowCount() != 1) {
            $pdo->query("ALTER TABLE affiliates ADD Discount VARCHAR(100)");
            $pdo->query("UPDATE affiliates SET Discount = 'onDiscountaffiliates'");
            echo "The Discount field was added ✅";
        }
        $Check_filde = $pdo->query("SHOW COLUMNS FROM affiliates LIKE 'price_Discount'");
        if (($Check_filde)->rowCount() != 1) {
            $pdo->query("ALTER TABLE affiliates ADD price_Discount VARCHAR(100)");
            echo "The price_Discount field was added ✅";
        }
        $Check_filde = $pdo->query("SHOW COLUMNS FROM affiliates LIKE 'status_commission'");
        if (($Check_filde)->rowCount() != 1) {
            $pdo->query("ALTER TABLE affiliates ADD status_commission VARCHAR(100)");
            $pdo->query("UPDATE affiliates SET status_commission = 'oncommission'");
            echo "The commission field was added ✅";
        }
    }
} catch (Exception $e) {
    file_put_contents('error_log', $e->getMessage());
}
try {
    $result = $pdo->query("SHOW TABLES LIKE 'shopSetting'");
    $table_exists = ($result->rowCount() > 0);
    $agent_cashback = json_encode(array(
        'n' => 0,
        'n2' => 0
    ));
    if (!$table_exists) {
        $result = $pdo->query("CREATE TABLE shopSetting (
        Namevalue varchar(500) PRIMARY KEY NOT NULL,
        value TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL)
        ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci");
        if (!$result) {
            echo "table shopSetting" . implode(' ', $pdo->errorInfo());
        }
        $pdo->query("INSERT INTO shopSetting (Namevalue,value) VALUES ('customvolmef','4000')");
        $pdo->query("INSERT INTO shopSetting (Namevalue,value) VALUES ('customvolmen','4000')");
        $pdo->query("INSERT INTO shopSetting (Namevalue,value) VALUES ('customvolmen2','4000')");
        $pdo->query("INSERT INTO shopSetting (Namevalue,value) VALUES ('statusextra','offextra')");
        $pdo->query("INSERT INTO shopSetting (Namevalue,value) VALUES ('customtimepricef','4000')");
        $pdo->query("INSERT INTO shopSetting (Namevalue,value) VALUES ('customtimepricen','4000')");
        $pdo->query("INSERT INTO shopSetting (Namevalue,value) VALUES ('customtimepricen2','4000')");
        $pdo->query("INSERT INTO shopSetting (Namevalue,value) VALUES ('statusdirectpabuy','ondirectbuy')");
        $pdo->query("INSERT INTO shopSetting (Namevalue,value) VALUES ('minbalancebuybulk','0')");
        $pdo->query("INSERT INTO shopSetting (Namevalue,value) VALUES ('statustimeextra','offtimeextraa')");
        $pdo->query("INSERT INTO shopSetting (Namevalue,value) VALUES ('statusdisorder','offdisorder')");
        // 💡 روشن/خاموش کردن اکانت ships on
        $pdo->query("INSERT INTO shopSetting (Namevalue,value) VALUES ('statuschangeservice','onstatus')");
        $pdo->query("INSERT INTO shopSetting (Namevalue,value) VALUES ('statuschangeservice_defon','1')");
        $pdo->query("INSERT INTO shopSetting (Namevalue,value) VALUES ('statusshowprice','offshowprice')");
        $pdo->query("INSERT INTO shopSetting (Namevalue,value) VALUES ('configshow','onconfig')");
        $pdo->query("INSERT INTO shopSetting (Namevalue,value) VALUES ('backserviecstatus','off')");
        $pdo->query("INSERT INTO shopSetting (Namevalue,value) VALUES ('chashbackextend','0')");
        $__q4 = $pdo->prepare("INSERT INTO shopSetting (Namevalue,value) VALUES ('chashbackextend_agent',?)");
        $__q4->bindValue(1, $agent_cashback, PDO::PARAM_STR);
        $__q4->execute();
    } else {
        $pdo->query("INSERT IGNORE INTO shopSetting (Namevalue,value) VALUES ('customvolmef','4000')");
        $pdo->query("INSERT IGNORE INTO shopSetting (Namevalue,value) VALUES ('customvolmen','4000')");
        $pdo->query("INSERT IGNORE INTO shopSetting (Namevalue,value) VALUES ('customvolmen2','4000')");
        $pdo->query("INSERT IGNORE INTO shopSetting (Namevalue,value) VALUES ('statusextra','offextra')");
        $pdo->query("INSERT IGNORE INTO shopSetting (Namevalue,value) VALUES ('statusdirectpabuy','ondirectbuy')");
        $pdo->query("INSERT IGNORE INTO shopSetting (Namevalue,value) VALUES ('minbalancebuybulk','0')");
        $pdo->query("INSERT IGNORE INTO shopSetting (Namevalue,value) VALUES ('statustimeextra','offtimeextraa')");
        $pdo->query("INSERT IGNORE INTO shopSetting (Namevalue,value) VALUES ('customtimepricef','4000')");
        $pdo->query("INSERT IGNORE INTO shopSetting (Namevalue,value) VALUES ('customtimepricen','4000')");
        $pdo->query("INSERT IGNORE INTO shopSetting (Namevalue,value) VALUES ('customtimepricen2','4000')");
        $pdo->query("INSERT IGNORE INTO shopSetting (Namevalue,value) VALUES ('statusdisorder','offdisorder')");
        $pdo->query("INSERT IGNORE INTO shopSetting (Namevalue,value) VALUES ('statuschangeservice','onstatus')");
        $pdo->query("INSERT IGNORE INTO shopSetting (Namevalue,value) VALUES ('statusshowprice','offshowprice')");
        $pdo->query("INSERT IGNORE INTO shopSetting (Namevalue,value) VALUES ('configshow','onconfig')");
        $pdo->query("INSERT IGNORE INTO shopSetting (Namevalue,value) VALUES ('backserviecstatus','off')");
        $pdo->query("INSERT IGNORE INTO shopSetting (Namevalue,value) VALUES ('chashbackextend','0')");
        $__q5 = $pdo->prepare("INSERT IGNORE INTO shopSetting (Namevalue,value) VALUES ('chashbackextend_agent',?)");
        $__q5->bindValue(1, $agent_cashback, PDO::PARAM_STR);
        $__q5->execute();



    }
} catch (Exception $e) {
    file_put_contents('error_log', $e->getMessage());
}
//----------------------- [ remove requests ] --------------------- //
try {
    $result = $pdo->query("SHOW TABLES LIKE 'cancel_service'");
    $table_exists = ($result->rowCount() > 0);

    if (!$table_exists) {
        $result = $pdo->query("CREATE TABLE cancel_service (
        id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        id_user varchar(500)  NOT NULL,
        username varchar(1000)  NOT NULL,
        description TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci  NOT NULL,
        status varchar(1000)  NOT NULL)
        ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci");
        if (!$result) {
            echo "table cancel_service" . implode(' ', $pdo->errorInfo());
        }
    }
} catch (Exception $e) {
    file_put_contents('error_log', $e->getMessage());
}
try {
    $result = $pdo->query("SHOW TABLES LIKE 'service_other'");
    $table_exists = ($result->rowCount() > 0);

    if (!$table_exists) {
        $result = $pdo->query("CREATE TABLE service_other (
        id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        id_user varchar(500)  NOT NULL,
        username varchar(1000)  NOT NULL,
        value varchar(1000)  NOT NULL,
        time varchar(200)  NOT NULL,
        price varchar(200)  NOT NULL,
        type varchar(1000)  NOT NULL,
        status varchar(200)  NOT NULL,
        output TEXT  NOT NULL)
        ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci");
        if (!$result) {
            echo "table service_other" . implode(' ', $pdo->errorInfo());
        }
    } else {
        $Check_filde = $pdo->query("SHOW COLUMNS FROM service_other LIKE 'price'");
        if (($Check_filde)->rowCount() != 1) {
            $pdo->query("ALTER TABLE service_other ADD price VARCHAR(200)");
            echo "The price field was added ✅";
        }
        $Check_filde = $pdo->query("SHOW COLUMNS FROM service_other LIKE 'status'");
        if (($Check_filde)->rowCount() != 1) {
            $pdo->query("ALTER TABLE service_other ADD status VARCHAR(200)");
            echo "The status field was added ✅";
        }
        $Check_filde = $pdo->query("SHOW COLUMNS FROM service_other LIKE 'output'");
        if (($Check_filde)->rowCount() != 1) {
            $pdo->query("ALTER TABLE service_other ADD output TEXT");
            echo "The output field was added ✅";
        }
    }
} catch (Exception $e) {
    file_put_contents('error_log', $e->getMessage());
}
try {
    $result = $pdo->query("SHOW TABLES LIKE 'card_number'");
    $table_exists = ($result->rowCount() > 0);

    if (!$table_exists) {
        $result = $pdo->query("CREATE TABLE card_number (
        cardnumber varchar(500) PRIMARY KEY,
        namecard  varchar(1000)  NOT NULL)
        CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        if (!$result) {
            echo "table x_ui" . implode(' ', $pdo->errorInfo());
        }
    }
    $columnInfo = $pdo->query("SHOW FULL COLUMNS FROM card_number LIKE 'namecard'");
    if ($columnInfo) {
        $column = $columnInfo->fetch(PDO::FETCH_ASSOC);
        $currentCollation = $column['Collation'] ?? '';
        if (empty($currentCollation) || stripos($currentCollation, 'utf8mb4') === false) {
            $pdo->query("ALTER TABLE card_number CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->query("ALTER TABLE card_number MODIFY cardnumber varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci PRIMARY KEY");
            $pdo->query("ALTER TABLE card_number MODIFY namecard varchar(1000) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL");
        }
        $columnInfo = null;
    }
} catch (Exception $e) {
    file_put_contents('error_log card_number', $e->getMessage());
}
try {
    $result = $pdo->query("SHOW TABLES LIKE 'Requestagent'");
    $table_exists = ($result->rowCount() > 0);

    if (!$table_exists) {
        $result = $pdo->query("CREATE TABLE Requestagent (
        id varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci PRIMARY KEY,
        username  varchar(500)  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
        time  varchar(500)  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
        Description  varchar(500)  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
        status  varchar(500)  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
        type  varchar(500)  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL)
        ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        if (!$result) {
            echo "table Requestagent" . implode(' ', $pdo->errorInfo());
        }
    } else {
        ensureTableUtf8mb4('Requestagent');
    }
} catch (Exception $e) {
    file_put_contents('error_log Requestagent', $e->getMessage());
}
try {
    $result = $pdo->query("SHOW TABLES LIKE 'topicid'");
    $table_exists = ($result->rowCount() > 0);
    if (!$table_exists) {
        $result = $pdo->query("CREATE TABLE topicid (
        report varchar(500) PRIMARY KEY NOT NULL,
        idreport TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL)
        ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci");
        if (!$result) {
            echo "table Requestagent" . implode(' ', $pdo->errorInfo());
        }
        $pdo->query("INSERT INTO topicid (idreport,report) VALUES ('0','buyreport')");
        $pdo->query("INSERT INTO topicid (idreport,report) VALUES ('0','otherservice')");
        $pdo->query("INSERT INTO topicid (idreport,report) VALUES ('0','paymentreport')");
        $pdo->query("INSERT INTO topicid (idreport,report) VALUES ('0','otherreport')");
        $pdo->query("INSERT INTO topicid (idreport,report) VALUES ('0','reporttest')");
        $pdo->query("INSERT INTO topicid (idreport,report) VALUES ('0','errorreport')");
        $pdo->query("INSERT INTO topicid (idreport,report) VALUES ('0','porsantreport')");
        $pdo->query("INSERT INTO topicid (idreport,report) VALUES ('0','reportnight')");
        $pdo->query("INSERT INTO topicid (idreport,report) VALUES ('0','reportcron')");
        $pdo->query("INSERT INTO topicid (idreport,report) VALUES ('0','backupfile')");
    } else {
        $pdo->query("INSERT IGNORE INTO topicid (idreport,report) VALUES ('0','buyreport')");
        $pdo->query("INSERT IGNORE INTO topicid (idreport,report) VALUES ('0','otherservice')");
        $pdo->query("INSERT IGNORE INTO topicid (idreport,report) VALUES ('0','paymentreport')");
        $pdo->query("INSERT IGNORE INTO topicid (idreport,report) VALUES ('0','otherreport')");
        $pdo->query("INSERT IGNORE INTO topicid (idreport,report) VALUES ('0','reporttest')");
        $pdo->query("INSERT IGNORE INTO topicid (idreport,report) VALUES ('0','errorreport')");
        $pdo->query("INSERT IGNORE INTO topicid (idreport,report) VALUES ('0','porsantreport')");
        $pdo->query("INSERT IGNORE INTO topicid (idreport,report) VALUES ('0','reportnight')");
        $pdo->query("INSERT IGNORE INTO topicid (idreport,report) VALUES ('0','reportcron')");
        $pdo->query("INSERT IGNORE INTO topicid (idreport,report) VALUES ('0','backupfile')");




    }
} catch (Exception $e) {
    file_put_contents('error_log topicid', $e->getMessage());
}
try {
    $result = $pdo->query("SHOW TABLES LIKE 'manualsell'");
    $table_exists = ($result->rowCount() > 0);
    if (!$table_exists) {
        $result = $pdo->query("CREATE TABLE manualsell (
        id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        codepanel  varchar(100)  NOT NULL,
        codeproduct  varchar(100)  NOT NULL,
        namerecord  varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci  NOT NULL,
        username  varchar(500)  NULL,
        contentrecord  TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci  NOT NULL,
        status  varchar(200)  NOT NULL)
        ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci");
        if (!$result) {
            echo "table manualsell" . implode(' ', $pdo->errorInfo());
        }
    }
} catch (Exception $e) {
    file_put_contents('error_log manualsell', $e->getMessage());
}
// 🎁 کانفیگ رایگان با دعوت
try {
    affrw_ensure_table();
} catch (Exception $e) {
    file_put_contents('error_log affiliate_reward', $e->getMessage());
}
// ⏱ اکانت ساعتی: payg_service, payg_charge and invoice.payg
try {
    payg_ensure_schema();
} catch (Exception $e) {
    file_put_contents('error_log payg', $e->getMessage());
}
//-----------------------------------------------------------------
try {

    $tableName = 'departman';
    $stmt = $pdo->prepare("SELECT 1 FROM information_schema.tables WHERE table_name = :tableName");
    $stmt->bindParam(':tableName', $tableName);
    $stmt->execute();
    $tableExists = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$tableExists) {
        $stmt = $pdo->prepare("CREATE TABLE $tableName (
            id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            idsupport VARCHAR(200) NOT NULL,
            name_departman VARCHAR(600) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci");
        $stmt->execute();
        $stmt = $pdo->prepare("INSERT INTO departman (idsupport,name_departman) VALUES (?, ?)");
        $stmt->execute([$adminnumber, $textbotlang['db_defaults']['departmanGeneral']]);
    }
} catch (PDOException $e) {
    file_put_contents('error_log departman', $e->getMessage());
}
try {

    $tableName = 'support_message';
    $stmt = $pdo->prepare("SELECT 1 FROM information_schema.tables WHERE table_name = :tableName");
    $stmt->bindParam(':tableName', $tableName);
    $stmt->execute();
    $tableExists = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$tableExists) {
        $stmt = $pdo->prepare("CREATE TABLE $tableName (
            id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            Tracking VARCHAR(100) NOT NULL,
            idsupport VARCHAR(100) NOT NULL,
            iduser VARCHAR(100) NOT NULL,
            name_departman VARCHAR(600) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
            text TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
            result TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
            time VARCHAR(200) NOT NULL,
            status ENUM('Answered','Pending','Unseen','Customerresponse','close') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci");
        $stmt->execute();
    } else {
        addFieldToTable("support_message", "result", "0", "TEXT");
    }
} catch (PDOException $e) {
    file_put_contents('error_log suppeor_message', $e->getMessage());
}
try {
    $result = $pdo->query("SHOW TABLES LIKE 'wheel_list'");
    $table_exists = ($result->rowCount() > 0);
    if (!$table_exists) {
        $result = $pdo->query("CREATE TABLE wheel_list (
        id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        id_user  varchar(200)  NOT NULL,
        time  varchar(200)  NOT NULL,
        first_name  varchar(200)  NOT NULL,
        wheel_code  varchar(200)  NOT NULL,
        price  varchar(200)  NOT NULL)
        ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci");
        if (!$result) {
            echo "table wheel_list" . implode(' ', $pdo->errorInfo());
        }
    }
} catch (Exception $e) {
    file_put_contents('error_log botsaz', $e->getMessage());
}
try {
    $result = $pdo->query("SHOW TABLES LIKE 'botsaz'");
    $table_exists = ($result->rowCount() > 0);
    if (!$table_exists) {
        $result = $pdo->query("CREATE TABLE botsaz (
        id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        id_user  varchar(200)  NOT NULL,
        bot_token  varchar(200)  NOT NULL,
        admin_ids  TEXT  NOT NULL,
        username  varchar(200)  NOT NULL,
        setting  TEXT  NULL,
        hide_panel  JSON  NOT NULL,
        time  varchar(200)  NOT NULL)
        ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci");
        if (!$result) {
            echo "table botsaz" . implode(' ', $pdo->errorInfo());
        }
    } else {
        addFieldToTable("botsaz", "hide_panel", "{}", "JSON");
    }
    addFieldToTable("botsaz", "webhook_secret", null, "VARCHAR(200) NOT NULL DEFAULT ''");
} catch (Exception $e) {
    file_put_contents('error_log botsaz', $e->getMessage());
}

try {
    $result = $pdo->query("SHOW TABLES LIKE 'app'");
    $table_exists = ($result->rowCount() > 0);
    if (!$table_exists) {
        $result = $pdo->query("CREATE TABLE app (
        id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        name  varchar(200)   CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
        link  varchar(200)  NOT NULL)
        ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci");
        if (!$result) {
            echo "table app" . implode(' ', $pdo->errorInfo());
        }
    }
} catch (Exception $e) {
    file_put_contents('error_log app', $e->getMessage());
}



try {
    $result = $pdo->query("SHOW TABLES LIKE 'logs_api'");
    $table_exists = ($result->rowCount() > 0);
    if (!$table_exists) {
        $result = $pdo->query("CREATE TABLE logs_api (
        id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        header JSON  NULL,
        data JSON  NULL,
        ip  varchar(200)  NOT NULL,
        time  varchar(200)  NOT NULL,
        actions  varchar(200)  NOT NULL)
        ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci");
        if (!$result) {
            echo "table logs_api" . implode(' ', $pdo->errorInfo());
        }
    }
} catch (Exception $e) {
    file_put_contents('error_log logs_api', $e->getMessage());
}
//----------------------- [ Category ] --------------------- //
try {
    $result = $pdo->query("SHOW TABLES LIKE 'category'");
    $table_exists = ($result->rowCount() > 0);

    if (!$table_exists) {
        $result = $pdo->query("CREATE TABLE category (
        id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        remark varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin  NOT NULL)
        ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_bin");
        if (!$result) {
            echo "table category" . implode(' ', $pdo->errorInfo());
        }
    }
} catch (Exception $e) {
    file_put_contents('error_log', $e->getMessage());
}
addFieldToTable("category", "lang", "all", "VARCHAR(20)");
addFieldToTable("user", "panel_submenu", "", "VARCHAR(100)");
try {
    $result = $pdo->query("SHOW TABLES LIKE 'currency'");
    if ($result->rowCount() == 0) {
        $pdo->query("CREATE TABLE currency (
        id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        code VARCHAR(10) NOT NULL UNIQUE,
        title VARCHAR(100) NOT NULL,
        symbol VARCHAR(20) NOT NULL,
        decimals TINYINT UNSIGNED NOT NULL DEFAULT 0,
        symbol_position VARCHAR(10) NOT NULL DEFAULT 'after'
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci");
        $seedcur = $pdo->prepare("INSERT IGNORE INTO currency (code,title,symbol,decimals,symbol_position) VALUES (?,?,?,?,?)");
        foreach ([
            ['IRT', 'تومان', 'تومان', 0, 'after'],
            ['USD', 'US Dollar', '$', 2, 'before'],
        ] as $rowcur) {
            $seedcur->execute($rowcur);
        }
    }
} catch (Exception $e) {
    file_put_contents('error_log', $e->getMessage());
}
addFieldToTable("setting", "lang_currency", '{"fa":"IRT","en":"USD"}', "TEXT");
addFieldToTable("setting", "lang_gateways", '{}', "TEXT");
addFieldToTable("setting", "gw_auto_currency", 'off', "TEXT");
addFieldToTable("setting", "topup_packages", '{}', "TEXT");
addFieldToTable("setting", "volumePctTiers", '[]', "TEXT");
addFieldToTable("setting", "topup_discounts", '{}', "TEXT");
addFieldToTable("setting", "topup_disc_notify", '{}', "TEXT");
addFieldToTable("setting", "topup_disc_groups", '{}', "TEXT");
addFieldToTable("user", "menu_sticker_id", "0", "VARCHAR(50)");
addFieldToTable("user", "menu_tap_id", "0", "VARCHAR(50)");
addFieldToTable("user", "topup_disc_msg_id", "0", "VARCHAR(50)");
addFieldToTable("user", "tp_edit_origin", "", "VARCHAR(20)");
addFieldToTable("user", "bt_sticker_id", "0", "VARCHAR(50)");
addFieldToTable("user", "bt_sticker_owner", "0", "VARCHAR(50)");
addFieldToTable("setting", "topup_captions", '{}', "TEXT");
addFieldToTable("setting", "topup_linkmsgs", '{}', "TEXT");
addFieldToTable("setting", "topup_range_captions", '{}', "TEXT");
addFieldToTable("setting", "topup_invoice_captions", '{}', "TEXT");
addFieldToTable("setting", "topup_invoice_expired_captions", '{}', "TEXT");
addFieldToTable("setting", "topup_invoice_btnstyle", '{}', "TEXT");
addFieldToTable("setting", "topup_minusd_captions", '{}', "TEXT");
addFieldToTable("setting", "topup_paid_alerts", '{}', "TEXT");
addFieldToTable("setting", "topup_notnumber_captions", '{}', "TEXT");
addFieldToTable("setting", "topup_notseen_captions", '{}', "TEXT");
addFieldToTable("setting", "topup_noaddress_captions", '{}', "TEXT");
addFieldToTable("setting", "topup_askhash_captions", '{}', "TEXT");
addFieldToTable("setting", "topup_hashbad_captions", '{}', "TEXT");
addFieldToTable("setting", "topup_group_methods", '{}', "TEXT");
addFieldToTable("setting", "topup_group_btnstyle", '{}', "TEXT");
addFieldToTable("setting", "topup_group_captions", '{}', "TEXT");
addFieldToTable("setting", "topup_memo_config", '{}', "TEXT");
addFieldToTable("user", "topup_range_msg_id", "0", "VARCHAR(50)");
addFieldToTable("user", "topup_custom_msg_id", "0", "VARCHAR(50)");
addFieldToTable("setting", "topup_minmax", '{}', "TEXT");
addFieldToTable("setting", "topup_btnstyle", '{}', "TEXT");
addFieldToTable("setting", "lang_gwsettings", '{}', "TEXT");
addFieldToTable("setting", "gw_currency_map", '{"card":"IRT","zarinpal":"IRT","aqayepardakht":"IRT","iranpay1":"IRT","iranpay3":"IRT","paymentnotverify":"IRT","plisio":"USD","nowpayment":"USD","digitaltron":"USD"}', "TEXT");
addFieldToTable("product", "currency", "IRT", "VARCHAR(10)");
addFieldToTable("user", "currency", "IRT", "VARCHAR(10)");
// ---- a wallet per currency (wallet_switch_lang in function.php) ----
// the wallets not in use, and the currency a payment or a service was priced in
// once its payer switched language - no default, an old row counts as its
// owner's current wallet
addFieldToTable("user", "wallets", null, "TEXT NULL");
// 🛡 the inviter a newcomer is still being verified for
addFieldToTable("user", "aff_pending", null, "VARCHAR(50) NULL");
// ⛔️ the referral plans an admin keeps this user out of as an inviter ('c' 💼, 'r' 🎁)
addFieldToTable("user", "aff_block", null, "VARCHAR(4) NULL");
addFieldToTable("Payment_report", "currency", null, "VARCHAR(10) NULL");
addFieldToTable("invoice", "currency", null, "VARCHAR(10) NULL");
// 🧩 حجم دلخواه (cvol.php): a panel's settings, the mark on a plan bought that way, the screen a user has open
addFieldToTable("marzban_panel", "cvol", null, "TEXT NULL");
addFieldToTable("invoice", "cvol", null, "VARCHAR(4) NULL");
addFieldToTable("user", "cvol_state", null, "TEXT NULL");
// 💳 پرداخت مستقیم (directpay.php): the invoice of a purchase paid directly, so the payment leaves the wallet alone
addFieldToTable("invoice", "pay_direct", "0", "VARCHAR(2)");
// the payment an invoice bought after paying was settled by (DirectPayment_settle)
addFieldToTable("invoice", "paid_order", null, "VARCHAR(200) NULL");
// ⚙️ a Rebecca panel's own service for test accounts (rbsvc.php); empty = the purchase one
addFieldToTable("marzban_panel", "rb_test_service", null, "VARCHAR(20) NULL");
addFieldToTable("setting", "wallet_migrated", "0", "VARCHAR(5)");
// 💱 converting a balance on a language switch - off until an admin turns it on
addFieldToTable("setting", "wallet_convert", '{}', "TEXT");
try {
    // cents: a dollar wallet holds $0.10
    $wm_col = $pdo->query("SHOW COLUMNS FROM `user` LIKE 'Balance'")->fetch(PDO::FETCH_ASSOC);
    if ($wm_col && stripos((string) $wm_col['Type'], 'decimal') === false) {
        $pdo->exec("ALTER TABLE `user` MODIFY `Balance` DECIMAL(20,2) NOT NULL DEFAULT 0");
    }
    // once: every wallet was labelled tomans, but an English user has always
    // been topped up and charged in dollars - label each with its language's
    // currency
    if ((string) $pdo->query("SELECT wallet_migrated FROM setting LIMIT 1")->fetchColumn() !== '1') {
        $wm_map = json_decode((string) $pdo->query("SELECT lang_currency FROM setting LIMIT 1")->fetchColumn(), true);
        $wm_st = $pdo->prepare("UPDATE user SET currency = ? WHERE lang = ?");
        foreach (is_array($wm_map) ? $wm_map : [] as $wm_lang => $wm_cur) {
            if ($wm_lang !== 'fa' && is_string($wm_cur) && $wm_cur !== '') {
                $wm_st->execute([$wm_cur, $wm_lang]);
            }
        }
        $pdo->exec("UPDATE setting SET wallet_migrated = '1'");
    }
} catch (Exception $e) {
    error_log("wallet migration: " . $e->getMessage());
}
addFieldToTable("channels", "id", null, "INT AUTO_INCREMENT PRIMARY KEY");
addFieldToTable("channels", "style", null, "VARCHAR(20)");
addFieldToTable("channels", "custom_text", null, "VARCHAR(300)");
addFieldToTable("channels", "emoji", null, "VARCHAR(50)");
addFieldToTable("channels", "icon_emoji", null, "VARCHAR(100)");
addFieldToTable("channels", "emoji_pos", null, "VARCHAR(10)");
addFieldToTable("channels", "hidden", null, "VARCHAR(5)");
addFieldToTable("setting", "channelButtonsOrder", null, "TEXT");
// channel-button looks for every language tab but fa (fa keeps the columns above)
addFieldToTable("setting", "channelButtonsLang", '{}', "TEXT");
addFieldToTable("setting", "close_sticker", '{}', "TEXT");
addFieldToTable("setting", "svc_node_usage", '{}', "TEXT");
addFieldToTable("setting", "shop_feature_lang", '{}', "TEXT");
// 💡 روشن/خاموش کردن اکانت is on by default now. A shop still on the old
// default (off) gets it on once - every language's own switch too; one turned
// off after this stays off (the marker row says it has run).
try {
    if (!$pdo->query("SELECT COUNT(*) FROM shopSetting WHERE Namevalue = 'statuschangeservice_defon'")->fetchColumn()) {
        $pdo->query("UPDATE shopSetting SET value = 'onstatus' WHERE Namevalue = 'statuschangeservice' AND value = 'offstatus'");
        $sc_map = json_decode((string) $pdo->query("SELECT shop_feature_lang FROM setting LIMIT 1")->fetchColumn(), true);
        if (is_array($sc_map) && is_array($sc_map['changgestatus'] ?? null)) {
            foreach ($sc_map['changgestatus'] as $sc_l => $sc_v) {
                if ($sc_v === 'offstatus') {
                    $sc_map['changgestatus'][$sc_l] = 'onstatus';
                }
            }
            $pdo->prepare("UPDATE setting SET shop_feature_lang = ?")->execute([json_encode($sc_map, JSON_UNESCAPED_UNICODE)]);
        }
        $pdo->query("INSERT IGNORE INTO shopSetting (Namevalue,value) VALUES ('statuschangeservice_defon','1')");
    }
} catch (Exception $e) {
    error_log('statuschangeservice default: ' . $e->getMessage());
}
addFieldToTable("setting", "feature_lang", '{}', "TEXT");
// 🌐 / 📲 and the app list are one setting for every place now: what was set on
// the per-place ones moves to the shared one (function.php)
try {
    subapps_merge_legacy();
} catch (Throwable $e) {
    error_log('subapps merge: ' . $e->getMessage());
}
// the warnings' button looks (🔋, and the test-account-ended button) are per
// language now: each tab keeps a copy of what it showed
try {
    volumepct_unshare_looks();
    genbtn_unshare_looks();
} catch (Throwable $e) {
    error_log('unshare looks: ' . $e->getMessage());
}
// Russian, Chinese and Turkmen are not languages of the bot any more (only fa
// and en have a lang/ file). Whoever was on one is on Persian - the language
// every missing text already falls back to - and the picker's list and the
// language -> currency map lose those codes. Safe to run again.
try {
    $pdo->query("UPDATE `user` SET lang = 'fa' WHERE lang IN ('ru', 'zh', 'tk')");
    $gone_row = $pdo->query("SELECT lang_switch, lang_currency FROM setting LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    if (is_array($gone_row)) {
        $gone_sw = json_decode((string) $gone_row['lang_switch'], true);
        if (is_array($gone_sw) && is_array($gone_sw['langs'] ?? null)) {
            $gone_sw['langs'] = array_values(array_intersect($gone_sw['langs'], ['fa', 'en']));
            $pdo->prepare("UPDATE setting SET lang_switch = ?")->execute([json_encode($gone_sw, JSON_UNESCAPED_UNICODE)]);
        }
        $gone_cur = json_decode((string) $gone_row['lang_currency'], true);
        if (is_array($gone_cur) && array_diff_key($gone_cur, ['fa' => 1, 'en' => 1])) {
            $pdo->prepare("UPDATE setting SET lang_currency = ?")->execute([json_encode(array_intersect_key($gone_cur, ['fa' => 1, 'en' => 1]), JSON_UNESCAPED_UNICODE)]);
        }
    }
} catch (Exception $e) {
    error_log('dropped languages: ' . $e->getMessage());
}
addFieldToTable("setting", "feature_lang_settings", '{}', "TEXT");
addFieldToTable("setting", "webhook_secret", null, "VARCHAR(200) NOT NULL DEFAULT ''");
// per-language app-download rows, same convention marzban_panel.lang already
// uses: a comma list of language codes, or 'all'/NULL/'' meaning every language
addFieldToTable("app", "lang", null, "VARCHAR(20)");
try {
    $result = $pdo->query("SHOW TABLES LIKE 'reagent_report'");
    $table_exists = ($result->rowCount() > 0);

    if (!$table_exists) {
        $result = $pdo->query("CREATE TABLE reagent_report (
        id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id BIGINT UNIQUE  NOT NULL,
        get_gift BOOL   NOT NULL,
        time varchar(50)  NOT NULL,
        reagent varchar(30)  NOT NULL
        )ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_bin");
        if (!$result) {
            echo "table affiliates" . implode(' ', $pdo->errorInfo());
        }
    }
} catch (Exception $e) {
    file_put_contents('error_log', $e->getMessage());
}



$balancemain = json_decode(select("PaySetting", "ValuePay", "NamePay", "maxbalance", "select")['ValuePay'], true);
if (!isset($balancemain['f'])) {
    $value = json_encode(array(
        "f" => "1000000",
        "n" => "1000000",
        "n2" => "1000000",
    ));
    $valuemain = json_encode(array(
        "f" => "20000",
        "n" => "20000",
        "n2" => "20000",
    ));
    update("PaySetting", "ValuePay", $value, "NamePay", "maxbalance");
    update("PaySetting", "ValuePay", $valuemain, "NamePay", "minbalance");
}
$pdo->query("ALTER TABLE `invoice` CHANGE `Volume` `Volume` VARCHAR(200)");
$pdo->query("ALTER TABLE `invoice` CHANGE `price_product` `price_product` VARCHAR(200)");
$pdo->query("ALTER TABLE `invoice` CHANGE `name_product` `name_product` VARCHAR(200)");
$pdo->query("ALTER TABLE `invoice` CHANGE `username` `username` VARCHAR(200)");
$pdo->query("ALTER TABLE `invoice` CHANGE `Service_location` `Service_location` VARCHAR(200)");
$pdo->query("ALTER TABLE `invoice` CHANGE `time_sell` `time_sell` VARCHAR(200)");
$pdo->query("ALTER TABLE marzban_panel MODIFY password_panel TEXT COLLATE utf8mb4_bin");
$pdo->query("ALTER TABLE marzban_panel MODIFY name_panel VARCHAR(255) COLLATE utf8mb4_bin");
try {
    $check = $pdo->query("SHOW COLUMNS FROM `marzban_panel` LIKE 'lang'");
} catch (Exception $e) {
    error_log("[CHECK:marzban_panel] error - " . $e->getMessage());
    exit;
}
if ($check && $check->rowCount() == 0) {
    $pdo->exec("ALTER TABLE `marzban_panel` ADD COLUMN `lang` VARCHAR(100) NULL");
}
$pdo->query("ALTER TABLE product MODIFY name_product VARCHAR(1000) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin");
$pdo->query("ALTER TABLE help MODIFY name_os VARCHAR(500) COLLATE utf8mb4_bin");
try {
    $check = $pdo->query("SHOW COLUMNS FROM `user` LIKE 'ref_code'");
} catch (Exception $e) {
    error_log("[CHECK:$tableName] ❌ " . $e->getMessage());
    exit;
}

if ($check && $check->rowCount() != 0) {
    $pdo->exec("ALTER TABLE `user` DROP `ref_code`");
}
// a main menu saved by an older bot (a restored backup of the original
// project) gets the buttons this one has - 💰 افزایش موجودی, 🌏 تغییر زبان
try {
    mainmenu_repair_saved();
} catch (\Throwable $e) {
    error_log('mainmenu repair: ' . $e->getMessage());
}
// the report group's topics: the ones in use go on the bot's own record, and
// copies it made beyond them - say on a fresh install that set the group up
// before this backup was put back - are deleted
try {
    report_topic_ledger_seed();
    report_topics_dedupe();
} catch (\Throwable $e) {
    error_log('report topics: ' . $e->getMessage());
}
// 🧩 حجم دلخواه takes the old «⚙️ سرویس دلخواه»'s place: each panel's settings
// are made from its old columns now, switched off (cvol_cfg). Done here rather
// than the first time a customer buys, because the resellers' bots read those
// columns and would keep offering the old one until then.
try {
    if (function_exists('cvol_cfg')) {
        foreach ($pdo->query("SELECT code_panel FROM marzban_panel WHERE cvol IS NULL OR cvol = ''")->fetchAll(PDO::FETCH_COLUMN) as $cv_code) {
            cvol_cfg((string) $cv_code);
        }
    }
} catch (\Throwable $e) {
    error_log('cvol migrate: ' . $e->getMessage());
}
// the newer features' columns, on install and update - they are also added at
// their first use, so a database restored by hand catches up by itself
try {
    foreach (['tk_ensure_schema', 'ar_ensure_schema', 'wb_ensure_schema', 'wt_ensure_schema'] as $xf_schema) {
        if (function_exists($xf_schema)) {
            $xf_schema();
        }
    }
} catch (\Throwable $e) {
    error_log('extras schema: ' . $e->getMessage());
}
// with its secret, and asking for chat_member too
webhook_register();
// 🆕 tell the admins what this update added (whatsnew.php), once
try {
    bot_whatsnew_announce(!empty($mz_fresh_install));
} catch (\Throwable $e) {
    error_log('whatsnew: ' . $e->getMessage());
}
// last: this database now matches this code (see ciro_schema_selfheal)
try {
    addFieldToTable("setting", "schema_mark", "", "VARCHAR(40)");
    update("setting", "schema_mark", ciro_schema_mark(), null, null);
    @unlink(__DIR__ . '/.schema_heal');
} catch (\Throwable $e) {
    error_log('schema_mark: ' . $e->getMessage());
}
