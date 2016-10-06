<?php
    header("Content-Type: text/html; charset=utf-8");
    // кэширование.
    $cache_time = 5; // Время жизни кэша в секундах
    define( '_cachaccess', 1 ); // анти-самовольный вызов файла кэша
    require_once "masters/read_cache.php"; // Пытаемся вывести содержимое кэша
?>
<?php
    class SaMAP{ // Вызывается в <head>
        public static function Init() // ГЛАВНЫЙ ЗАГРУЗЧИК СКРИПТА
        {
            // данные соединения
            $connectionHost = 'localhost';
            $connectionUser = 'servakorg_samp';
            $connectionDb = 'servakorg_samp';
            $connectionPassword = '12345678';
            
            $connection = new mysqli($connectionHost,$connectionUser,$connectionPassword,$connectionDb);
            if (mysqli_connect_errno()) {
                echo "Подключение невозможно: ".mysqli_connect_error();
            }
			mysqli_query ( $connection, 'SET NAMES utf8');
			
            // Загружать ресурсы здесь
            GangZones::Load($connection);
            Homes::Load($connection);
            Businesses::Load($connection);

            mysqli_close ($connection);
        }
        
        public static $engineName = "SaMAP Mini";
        
        public static function EchoVersion()
        {
            echo ''.self::$engineName;
        }
        
        public static function PrintInfo()
        {
        print "
        <!-- 
               ********\t\t\t\t\t********
               ********\t".self::$engineName."\t\t\t********
               ********\t©: Dmitry Sheenko\t\t********
               ********\tLicense: GNU Lesser General Public License, version 2.1\t\t********
               ********\tК Трехлетию Типичного скриптера SAMP http://vk.com/ts_samp\t********
               ********\t\t\t\t\t********
        -->
        ";
        }
    }

    abstract class Map{
        // уменьшение реального масштаба карты в n раз
        const Delim_Pos_CorrectionValue = 6;
        // коррекция значений
        const X_CorrectionValue = 7; 
        const Y_CorrectionValue = -6;
    }

    abstract class Object extends Map{
        // 2d samap params
        public $SamapX;
        public $SamapY; 
        
        protected function FromGtaPosToSaMapPos($posx, $posy) { // перевод размеров
            $this->SamapX = 0.0-self::X_CorrectionValue; // коррекция
            $this->SamapY = 0.0+self::Y_CorrectionValue; // коррекция
        
            $this->SamapX += ( $posx / self::Delim_Pos_CorrectionValue) + 500;
            $this->SamapY -= ( $posy / self::Delim_Pos_CorrectionValue) - 500;
        }
    }
  
    class Home extends Object{
        // house params from db
        public $Id; // id дома 
        public $ClassId; // id класса дома
        public $HasOwner; // имеет ли владельца
        public $Name1; // имя хозяина
        
        // posx, posy - координаты дома из БД
        // id - id дома из БД
        // ownerid - id владельца дома из БД
        // classid - id класса дома из БД
        // name1, - имя владельца из бд
        public function Home($posx, $posy, $id, $ownerid, $classid, $name1)
        {
            $this->Id = $id;
            $this->ClassId = $classid;
            
            if(strlen($name1) < 1){ $name1 = "=Не найден в БД="; }
                
            $this->Name1 = $name1;

            if($ownerid == 0) { $this->HasOwner = false;}
            else { $this->HasOwner = true; } 

            $this->FromGtaPosToSaMapPos($posx, $posy); // перевести размеры карты gta в размеры карты samap
        }
        
        public function getPriceByClass($classid)
        {
            $ClassPrices = array(
                '1' => '500000',
                '2' => '350000',
                '3' => '150000',
            );
            
            return $ClassPrices[$classid];
        }
        
        public function getClassLit($classid)
        {
            $ClassLits = array(
                '1' => 'A',
                '2' => 'B',
                '3' => 'C',
            );
            
            return $ClassLits[$classid];
        }
        
        public function getTitle()
        {
            $titletext = '';
            $titletext .= "Дом № ".$this->Id."\n\n";
            $titletext .= "Класс: ".$this->getClassLit($this->ClassId)."\n";
            switch($this->HasOwner)
            {
                case true:
                    $titletext .= "Владелец: ".$this->Name1."\n";
                break;
                case false:
                    $titletext .= "Стоимость: ".$this->getPriceByClass($this->ClassId)."$\n";
                break;
            }
            return $titletext;
        }
        
        public function Draw()
        {
            echo '<div ';
            switch($this->HasOwner)
            {
                case true:
                    echo 'class = "e home occupied"';
                break;
                
                case false:
                    echo 'class = "e home free" ';
                break;
            }
            echo 'style = "top: '.$this->SamapY.'px; left: '.$this->SamapX.'px" ';
            echo 'title = "'.$this->getTitle().'"';
            echo '></div>';
        }
    }
    
    class Homes{ // менеджер работы с классом home
        public static function Load($connectionVar) // загрузить дома из БД
        {
            $query = "SELECT house.id, house.classid, house.pid, house.icox, house.icoy, account.pname name1, account.family name2 FROM house LEFT JOIN account ON house.pid = account.id";
            $result = $connectionVar->query($query);
            while($row = mysqli_fetch_array($result))
            {
                // $posx, $posy, $id, $ownerid, $lid, $close, $classid, $name1, $name2
                $newHome = new Home(
                    $row['icox'],
                    $row['icoy'], 
                    $row['id'],
                    $row['pid'],
                    $row['classid'], 
                    $row['name1']
                );
                $newHome->Draw();
            }
        }
    }
    // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
    class Business extends Object{ // модель
 		public $SellPrice; // цена продажи
        public $Name; // название бизнеса
        public $OwnerName; // имя владельца
        public $Id; // id бизнеса

        public $HasOwner; // есть ли владелец

        // posx, posy - координаты бизнеса из БД
        // id - id бизнеса из БД
        // ownerid - id владельца бизнеса из БД
        // classid - id класса дома из БД
        // name - название бизнеса из бд
        // ownername - имя владельца бизнеса из бд
        // sellprice - стоимость бизнеса из бд
        public function Business($posx, $posy, $id, $ownerid, $name, $ownername, $sellprice)
        {
            $this->Id = $id;
            
            if(strlen($ownername) < 1){ $ownername = "=Не найден в БД="; }
            
            $this->OwnerName = $ownername;
            $this->Name = $name;
            $this->SellPrice = $sellprice;

            if($ownerid != 0) {$this->HasOwner = true;}
            else {$this->HasOwner = false;}

            $this->FromGtaPosToSaMapPos($posx, $posy); // перевести размеры карты gta в размеры карты samap
        }
        
        public function getTitle()
        {
            $titletext = '';
            $titletext .= "Предприятие № ".$this->Id."\n\n";
            $titletext .= "Название: ".$this->Name."\n";
            switch($this->HasOwner)
            {
                case true:
                    $titletext .= "Владелец: ".$this->OwnerName."\n";
                break;
                case false:
                    $titletext .= "Стоимость: ".$this->SellPrice."$\n";
                break;
            }
            return $titletext;
        }
        
        public function Draw()
        {
            echo '<div ';
            switch($this->HasOwner)
            {
                case true:
                    echo 'class = "e business bought"';
                break;
                
                case false:
                    echo 'class = "e business free" ';
                break;
            }
            echo 'style = "top: '.$this->SamapY.'px; left: '.$this->SamapX.'px" ';
            echo 'title = "'.$this->getTitle().'"';
            echo '></div>';
        }
	}

	class Businesses{ //менеджер по работе с моделью
	    public static function Load($connectionVar) 
	    {
            // загружаем из БД
            $query = "SELECT business.id, business.name, business.ownerid, business.x, business.y, business.sellprice, account.pname ownername FROM business LEFT JOIN account ON business.ownerid = account.id";
            $result = $connectionVar->query($query);
            while($row = mysqli_fetch_array($result))
            {
                // $posx, $posy, $id, $ownerid, $name, $ownername, $sellprice
                $newBus = new Business(
                    $row['x'],
                    $row['y'],
                    $row['id'],
                    $row['ownerid'],
                    $row['name'],
                    $row['ownername'],
                    $row['sellprice']
                );
                $newBus->Draw();
            }
		}
	}
	
	class GangZone extends Object{
	    public $Fractions = array(
            '5' => array("name" => 'Varrios Los Aztecas', "color" => "cyan"),
            '6' => array("name" => 'Los Santos Vagos', "color" => "yellow"),
            '4' => array("name" => 'Grove Street Families', "color" => "green"),
            '3' => array("name" => 'San Fierro Rifa', "color" => "blue"),
            '7' => array("name" => 'East Side Ballas', "color" => "purple"),
        );
	    
        public $Id;
        public $ownerId;
        
        public $Height = 0.0;
        public $Width = 0.0;
        
        public function GangZone($id, $minx, $miny, $maxx, $maxy, $ownerid)
        {
            $this->Id = $id;
            $this->ownerId = $ownerid;

            $this->FromGtaPosToSaMapPos($maxx, $maxy);
            $changedMaxX = $this->SamapX;
            $changedMaxY = $this->SamapY;
            
            $this->FromGtaPosToSaMapPos($minx, $miny); // перевести размеры карты gta в размеры карты samap
            $changedMinX = $this->SamapX;
            $changedMinY = $this->SamapY;
            
            $this->Width = abs($changedMaxX - $changedMinX);
            $this->Height = abs($changedMaxY - $changedMinY);
        }
        
        public function getOwnerName($ownerid){ return $this->Fractions[$ownerid]["name"]; }
        public function getOwnerColor($ownerid){ return $this->Fractions[$ownerid]["color"]; }
        
        public function getTitle()
        {
            $titletext = '';
            $titletext .= "Территория № ".$this->Id."\n\n";
            $titletext .= "Под контролем:\n".$this->getOwnerName($this->ownerId)."\n";
            return $titletext;
        }
        
        public function Draw()
        {
            echo '<div ';
            echo 'class = "e gangzone"';
            echo 'style = "top: '.$this->SamapY.'px; left: '.$this->SamapX.'px; width: '.$this->Width.'px; height: '.$this->Height.'px; background-color: '.$this->getOwnerColor($this->ownerId).'" ';
            echo 'title = "'.$this->getTitle().'"';
            echo '></div>';
        }
    }
    
    class GangZones{
        public static function Load($connectionVar)
        {
            $query = "SELECT id, ginfo1, ginfo2, ginfo3, ginfo4, fraction FROM gangzone";
            $result = $connectionVar->query($query);
            while($row = mysqli_fetch_array($result))
            {
                //$id, $minx, $miny, $maxx, $maxy, $ownerid
                $newGz = new GangZone(
                    $row['id'],
                    $row['ginfo1'],
                    $row['ginfo2'],
                    $row['ginfo3'],
                    $row['ginfo4'],
                    $row['fraction']
                );
                $newGz->Draw();
            }
        }
    }
	
    SaMAP::PrintInfo();
?>

<style>
    .e.home.free{ background: url('images/Icon_31.gif'); }
    .e.home.occupied{ background: url('images/Icon_32.gif'); }
    
    .e.business.free{ background-image: url(images/Icon_52.gif); }
    .e.business.bought{ background-image: url(images/Icon_36.gif); }
    
    .e.gangzone{ opacity: 0.4; }
    .e.gangzone:hover{ opacity: 0.7 !important;} 
</style>

<!DOCTYPE html>
<!--[if lt IE 7]>      <html class="no-js lt-ie9 lt-ie8 lt-ie7"> <![endif]-->
<!--[if IE 7]>         <html class="no-js lt-ie9 lt-ie8"> <![endif]-->
<!--[if IE 8]>         <html class="no-js lt-ie9"> <![endif]-->
<!--[if gt IE 8]><!--> <html class="no-js"> <!--<![endif]-->
<html lang="ru">
    <head>
        <meta charset="utf-8">
        <meta name="description" content="Интерактивная карта сервера Your server name"> 
        <meta name="author" content="Dmitry Sheenko">
        <link rel="shortcut icon" href="favicon.png" type="image/x-icon">
        <link type="text/css" rel="stylesheet" href="style.css">
        <!--[if lt IE 9]><script src="http://html5shiv.googlecode.com/svn/trunk/html5.js"></script><![endif]-->
        <title>Карта сервера Your server name</title>
        <link rel="stylesheet" href="//code.jquery.com/ui/1.11.3/themes/smoothness/jquery-ui.min.css">
        <script src="scripts/jquery-1.11.3.min.js" type = "text/javascript"></script>
        <script src="scripts/jquery-ui.min.js" type = "text/javascript"></script>
        
        <script>
        var samap_image = [
            {
                name: "Спутник", 
                imgsrc: "images/sa-1000-1000.jpg", 
                bodyscr: "images/sea14.jpg"
            },
            {
                name: "Карта", 
                imgsrc: "images/sa_map_1000_1000.jpg", 
                bodyscr: "images/sa_blue_square.png",
                showDefault: true
            }
        ];
        
        // index от samap_image
        function setImagesByIndex(index)
        {
            var samapimg = document.getElementById("samap");
            document.body.style.backgroundImage = "url("+samap_image[index]["bodyscr"]+")";
            samapimg.style.backgroundImage = "url("+samap_image[index]["imgsrc"]+")";
        }
        
        function CreateImagesSwitchButtons(parent)
        {
            samap_image.forEach(function(item, i) {
                var button = document.createElement('BUTTON');
                button.innerHTML = item["name"];
                button.onclick = function(){
                    setImagesByIndex(i);
                }
                parent.appendChild(button);
                if(item["showDefault"] === true) setImagesByIndex(i);
            });
        }
        
        window.onload = function()
        {
            CreateImagesSwitchButtons(document.getElementById('samap-menu'));
        }
        </script>
        
    </head>

    <body>
        <script>
        $(function() {
            $( "#samap" ).tooltip({
              position: {
                my: "center bottom-20",
                at: "center top",
                using: function( position, feedback ) {
                  $( this ).css( position );
                  $( "<div>" )
                    .addClass( "arrow" )
                    .addClass( feedback.vertical )
                    .addClass( feedback.horizontal )
                    .appendTo( this );
                }
              }
            });
        });
        </script>
    
        <div id = "samap-menu"></div>
        <div id = "samap">
            <?php 
                SaMAP::Init(); // поехали... 
            ?> 
        </div>
    </body>
    <!-- <?php SaMAP::EchoVersion(); ?>-->
</html>

<?php
    define( '_cachaccess', 1 ); // анти-самовольный вызов файла кэша
    require_once "masters/write_cache.php"; // Здесь идёт сохранение сгенерированной страницы в кэш
?>