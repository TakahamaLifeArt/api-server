<?php
/*
*	Calendar Class
*
*/
require_once $_SERVER['DOCUMENT_ROOT'].'/../cgi-bin/jd/japaneseDate.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/../cgi-bin/config.php';

class myCalendar extends japaneseDate {
    private $year = 0;
    private $month = 0;
 
    /**
     * Calendar
     * @param   $year       年
     * @param   $month      月
     */
     public function __construct($year=0, $month=0) {
        if (!checkdate($month, 1, $year)) {
            $year = date('Y');
            $month = date('m');
        }
 
        $this->year = (int)$year;
        $this->month = (int)$month;
        
        parent::japaneseDate();
    }
 
    /**
     * _days
     * 当月の日数取得（閏年対応）
     */
    private function _numDays() {
        return date('d', mktime(0, 0, 0, $this->month + 1, 0, $this->year));
    }
 
    /**
     * _first_day
     * 当月初めの曜日
     */
     private function _firstDay() {
        return date('w', mktime(0, 0, 0, $this->month, 1, $this->year));
    }
 
    /**
     * _last_day
     * 当月終わりの曜日
     */
     private function _lastDay() {
        return date('w', mktime(0, 0, 0, $this->month, $this->_numDays(), $this->year));
    }
 
    /**
     * makeCalendar
     * カレンダーの内容表示
     */
    public function makeCalendar() {
        // init
        $holiday = parent::getHolidayList(mktime(0, 0, 0, $this->month, 1, $this->year));
        list($Y1,$M1,$D1) = explode('/', _FROM_HOLIDAY);
        list($Y2,$M2,$D2) = explode('/', _TO_HOLIDAY);
        $_FROM = mktime(0, 0, 0, $M1, $D1, $Y1);
        $_TO = mktime(0, 0, 0, $M2, $D2, $Y2);
        
        $days = (int)$this->_numDays();
        $firstDay = (int)$this->_firstDay();
        $lastDay = (int)$this->_lastDay();
 
        $lastWeekDays = ($days + $firstDay) % 7;
        if ($lastWeekDays == 0) {
            $weeks = ($days + $firstDay) / 7;
        } else {
            $weeks = ceil(($days + $firstDay) / 7);
        }
 
        // view
        $i = 0;
        $j = 0;
        $day = 0;
        while ($i < $weeks) {
            $j = 0;
            $calendar .= "<tr>";
            while ($j < 7) {
                if (($i == 0 && $j < $firstDay) || ($i == $weeks - 1 && $j > $lastDay)) {
                    $calendar .= "<td> </td>";
                } else {
                    $day++;
                    $cur = mktime(0, 0, 0, $this->month, $day, $this->year);
                	if(!empty($holiday[$day]) || ($_FROM<=$cur && $cur<=$_TO)){
                		$calendar .= "<td class=\"off";
                	}else if($j==0){
                		$calendar .= "<td class=\"sun";
	                }else if($j==6){
	                	$calendar .= "<td class=\"sat";
	                }else{
	                	$calendar .= "<td class=\"";
	                }
	                
	                if($day==date('j') && $this->month==date('n')){
	                	$calendar .= " today\"><div>{$day}</div></td>";
	                }else{
	                	$calendar .= "\">{$day}</td>";
	                }
                }                
                $j++;
            }
            $calendar .= "</tr>";
            $i++;
        }
 
        return $calendar;
    }
 
}
?>
