<?php
	/* 안양대 드라이버 */
	class timetableDriver extends timetable {
		public $update_type = array('excel');
		function init() {
			$this->days = array('월', '화', '수', '목', '금', '토');
		}

		function findStartRow($data) {
			if($data['A'] == 'No' && $data['B'] == '과목코드') return 1;
			return false;
		}
		
		function getPeriodStartTime($period) {
			if($period < 1) return FALSE;
			if($period <= 8) return intval(($period-1) * 60 + 540); // 1~8 교시 일경우
			else if($period <= 14) return intval(($period-9) * 55 + 1020); // 9~14 교시 일경우
			else return 9999;
		}

		function getPeriodEndTime($period) {
			if($period < 1) return FALSE;
			if($period <= 8) return intval(($period * 60) + 540 - 10); // 1~8 교시 일경우
			else if($period <= 14) return intval(($period - 8) * 55 + 1020 - 5); // 9~14 교시 일경우
			else return 9999;
		}
		
		//엑셀 데이터 읽어오는 함수
		function parseExcelData($data) {
			$return = new stdClass(); //return 객체 생성
			
			$return->major = new stdClass();
			if(!$data['K']) $return->major->name = '미지정';
			else $return->major->name = $data['K']; //전공명
			
			
			$return->course = new stdClass();
			$return->course->name = $data['C']; //과목명
			$return->course->codes = $data['B']; //과목코드
			$return->course->category = $data['F']; //이수구분
			$return->course->grade = $data['D']; //학년
			$return->course->point = $data['L']; //학점
			$return->course->desc = ''; //비고

			//정수형으로 변환
			$return->course->grade = intval($return->course->grade);
			$return->course->point = intval($return->course->point);

			//분반 추가
			$return->lecture = new stdClass();
			$return->lecture->name = $return->course->name.'-'.$data['E']; //과목명-분반
			$return->lecture->codes = $return->course->codes.'-'.$data['E']; //과목코드-분반

			$return->professor = new stdClass();
			$return->professor->name = $data['I']; //교수명
			$return->professor->codes = $data['H']; //교수코드

			$return->time = array();
			$time = $data['S']; //강의시간
			if(!$time) return $return;

			$times = array(); //배열 선언
			
			$tokenNumber1 = strpos($time, "),");
			$tokenNumber2 = strrpos($time, "),");
			
			if ($tokenNumber1 == $tokenNumber2) $tokenNumber2 = FALSE;
			
			if ($tokenNumber1 == TRUE && $tokenNumber2 == TRUE){ //다중 시간표일때
				$tokenNumber1 += 2; // ),을 찾았을 경우에는 나누기 위해서
				$tokenNumber2 += 2; 
				$times[0] = substr($time, 0, $tokenNumber1 - 1); //첫번째 강의실, 시간
				$times[1] = substr($time, $tokenNumber1, (strlen($time) - $tokenNumber2)); //두번째 강의실, 시간
				$times[2] = substr($time, ((strlen($time) - $tokenNumber1) + 1), strlen($time)); //세번째 강의실, 시간
			}
			else if($tokenNumber1 == TRUE && $tokenNumber2 == FALSE){ // 두개 시간표일때
				$tokenNumber1 += 2; // ),을 찾았을 경우에는 나누기 위해서 
				$times[0] = substr($time, 0, $tokenNumber1 - 1); //첫번째 강의실, 시간
				$times[1] = substr($time, $tokenNumber1, (strlen($time) - $tokenNumber1)); //두번째 강의실, 시간
				
				if ($times[0] == "(,)"){
					$times[0] = $times[1];
					$times[1] = NULL;
				}
			}
			else { //단일 시간표일때
				$times[0] = $time; //시간 저장
			}
			
			foreach($times as $key => $val) {
				$vals = explode(':', $val); // : 를 기준으로 자름, vals[0] == 강의실, vals[1] == 시간표
				$classroom_temp = explode('-', $vals[0]); // -를 기준으로 자름, classroom_temp[0] == 건물명, classroom_temp[1] == 호수
				$classtime_temp = explode('(', $vals[1]); // (를 기준으로 자름, $classtime_temp[0] == 요일, $classtime_temp[1] == 교시
				
				$days = array_search ($classtime_temp[0], $this->days);		
				if($days === false) $days = -1; //요일을 찾았는데 없을 경우
				
				$obj = new stdClass();
				$obj->day = $days;
				
				$classtime_temp[1] = substr ( $classtime_temp[1], 0, (strlen($classtime_temp[1]) - 1));
				$classtime = explode(',', $classtime_temp[1]);
				
				$obj->start = $this->getPeriodStartTime(min($classtime)); //시작 시간 구하기
				$obj->end = $this->getPeriodEndTime(max($classtime)); //끝 시간 구하기
				
				$obj->classroom = $obj->classroom_building = $classroom_temp[0]." ".$classroom_temp[1]."호";
				$return->time[] = $obj;
			}
			return $return;
		}
	}
?>