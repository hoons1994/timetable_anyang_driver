<?php
/* 안양대 드라이버 */
#[\AllowDynamicProperties]
class timetableDriver extends timetable {
    public $update_type = array('excel');

    function init() {
        $this->days = array('월', '화', '수', '목', '금', '토');
    }

    function findStartRow($data) {
        if (($data['A'] ?? null) === 'No' && ($data['B'] ?? null) === '과목코드') return 1;
        return false;
    }

    function getPeriodStartTime($period) {
        $period = intval($period);
        if ($period < 1) return false;
        if ($period <= 8) return intval(($period - 1) * 60 + 540); // 1~8 교시일 경우
        if ($period <= 14) return intval(($period - 9) * 55 + 1020); // 9~14 교시일 경우
        return 9999;
    }

    function getPeriodEndTime($period) {
        $period = intval($period);
        if ($period < 1) return false;
        if ($period <= 8) return intval(($period * 60) + 540 - 10); // 1~8 교시일 경우
        if ($period <= 14) return intval(($period - 8) * 55 + 1020 - 5); // 9~14 교시일 경우
        return 9999;
    }

    // 엑셀 데이터 읽어오는 함수
    function parseExcelData($data) {
        $return = new stdClass(); // return 객체 생성

        $return->major = new stdClass();
        $majorName = trim((string)($data['K'] ?? ''));
        $return->major->name = $majorName !== '' ? $majorName : '미지정'; // 전공명

        $return->course = new stdClass();
        $return->course->name = (string)($data['C'] ?? ''); // 과목명
        $return->course->codes = (string)($data['B'] ?? ''); // 과목코드
        $return->course->category = (string)($data['F'] ?? ''); // 이수구분
        $return->course->grade = intval($data['D'] ?? 0); // 학년
        $return->course->point = intval($data['L'] ?? 0); // 학점
        $return->course->desc = ''; // 비고

        // 분반 추가
        $division = (string)($data['E'] ?? '');
        $return->lecture = new stdClass();
        $return->lecture->name = $return->course->name . '-' . $division; // 과목명-분반
        $return->lecture->codes = $return->course->codes . '-' . $division; // 과목코드-분반

        $return->professor = new stdClass();
        $return->professor->name = (string)($data['I'] ?? ''); // 교수명
        $return->professor->codes = (string)($data['H'] ?? ''); // 교수코드

        $return->time = array();
        $time = trim((string)($data['S'] ?? '')); // 강의시간
        if ($time === '') return $return;

        // 각 강의시간 항목은 닫는 괄호 뒤의 쉼표를 기준으로 분리한다.
        // 기존 코드는 최대 3개 항목만 처리했고 세 번째 항목의 시작 위치 계산이 취약했다.
        $times = preg_split('/(?<=\)),\s*/', $time);
        if ($times === false) return $return;

        foreach ($times as $val) {
            $val = trim((string)$val);
            if ($val === '' || $val === '(,)') continue;

            // 첫 번째 ':'만 구분자로 사용하여 예상치 못한 추가 ':'가 있어도 안전하게 처리한다.
            $vals = explode(':', $val, 2);
            if (count($vals) !== 2) continue;

            $classroomText = trim($vals[0]);
            $classTimeText = trim($vals[1]);
            if ($classTimeText === '') continue;

            // 강의실: 건물명-호수
            $classroomTemp = explode('-', $classroomText, 2);
            $building = trim($classroomTemp[0] ?? '');
            $room = trim($classroomTemp[1] ?? '');

            // 시간: 요일(교시,교시...)
            $classTimeTemp = explode('(', $classTimeText, 2);
            if (count($classTimeTemp) !== 2) continue;

            $dayName = trim($classTimeTemp[0]);
            $periodText = rtrim(trim($classTimeTemp[1]), ") \t\n\r\0\x0B");
            if ($periodText === '') continue;

            $days = array_search($dayName, $this->days, true);
            if ($days === false) $days = -1; // 요일을 찾았는데 없을 경우

            $classTimes = array();
            foreach (explode(',', $periodText) as $period) {
                $period = trim($period);
                if ($period === '' || !is_numeric($period)) continue;

                $period = intval($period);
                if ($period > 0) $classTimes[] = $period;
            }
            if (!$classTimes) continue;

            $obj = new stdClass();
            $obj->day = $days;
            $obj->start = $this->getPeriodStartTime(min($classTimes)); // 시작 시간 구하기
            $obj->end = $this->getPeriodEndTime(max($classTimes)); // 끝 시간 구하기

            if ($room !== '') {
                $obj->classroom = $obj->classroom_building = trim($building . ' ' . $room . '호');
            } else {
                $obj->classroom = $obj->classroom_building = $building;
            }

            $return->time[] = $obj;
        }

        return $return;
    }
}
