<?php
require_once __DIR__ . '/../core/Database.php';

class TestScoringService
{
    public function calculate($employeeId, $testId, $answersJson, $assignmentId = null): array
    {
        $employeeId=(int)$employeeId;$testId=(int)$testId;$assignmentId=$assignmentId?(int)$assignmentId:null;
        $test=Database::fetch('SELECT * FROM hr_assessment_tests WHERE id=?',[$testId]);
        if(!$test) throw new RuntimeException('test_not_found');
        $questions=Database::fetchAll('SELECT * FROM hr_assessment_questions WHERE test_id=? AND active=1 ORDER BY sort_order,id',[$testId]);
        $answers=is_array($answersJson)?$answersJson:json_decode((string)$answersJson,true);
        if(!is_array($answers))$answers=[];
        [$raw,$max]=$this->calculateDimensionScores($test,$questions,$answers);
        $normalized=$this->normalizeScores($raw,$max);
        $final=$this->generateFinalResult((string)$test['code'],$normalized);
        $risk=$this->getRiskLevel((string)$test['code'],$normalized);
        $recommendation=$this->generateRecommendation((string)$test['code'],$normalized,['employee_id'=>$employeeId]);
        $result=['raw_scores'=>$raw,'max_scores'=>$max,'normalized_scores'=>$normalized,'final_result'=>$final,'risk_level'=>$risk,'profile_summary'=>$this->profileSummary((string)$test['code'],$normalized,$final),'recommendation'=>$recommendation];
        if($assignmentId){
            Database::execute('INSERT INTO hr_assessment_results(assignment_id,employee_id,test_id,raw_answers_json,calculated_scores_json,normalized_scores_json,final_result,risk_level,profile_summary,recommendation_text,calculated_at,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,NOW(),NOW())',[$assignmentId,$employeeId,$testId,json_encode($answers,JSON_UNESCAPED_UNICODE),json_encode($raw,JSON_UNESCAPED_UNICODE),json_encode($normalized,JSON_UNESCAPED_UNICODE),$final,$risk,$result['profile_summary'],$recommendation]);
            $result['result_id']=(int)Database::lastInsertId();
        }
        return $result;
    }

    public function calculateDimensionScores($test, $questions, $answers): array
    {
        $raw=[];$max=[];
        foreach($questions as $q){
            $dimension=(string)($q['dimension_key']??'general');if($dimension==='')$dimension='general';
            $value=$answers[(string)$q['id']]??$answers[(int)$q['id']]??null;if($value===null||$value==='')continue;
            $type=(string)$q['answer_type'];$itemMax=$this->answerMax($type);$numeric=0;
            if($type==='choice'&&$q['correct_answer']!==null){$numeric=(string)$value===(string)$q['correct_answer']?1:0;$itemMax=1;}
            elseif($type==='yesno'){$numeric=in_array((string)$value,['1','yes','بله'],true)?1:0;$itemMax=1;}
            elseif($type==='text')continue;
            else{$numeric=max(1,min($itemMax,(float)$value));if((int)$q['reverse_score'])$numeric=$this->applyReverseScore($numeric,$type);}
            $weight=(float)($q['weight']??1);$raw[$dimension]=($raw[$dimension]??0)+$numeric*$weight;$max[$dimension]=($max[$dimension]??0)+$itemMax*$weight;
        }
        return [$raw,$max];
    }

    public function normalizeScores($rawScores, $maxScores): array
    {
        $out=[];foreach((array)$maxScores as $key=>$max)$out[$key]=(float)$max>0?round(min(100,max(0,((float)($rawScores[$key]??0)/(float)$max)*100)),2):0;return $out;
    }

    public function applyReverseScore($value, $answerType)
    {
        $max=$this->answerMax((string)$answerType);return ($max+1)-(float)$value;
    }

    public function generateFinalResult($testCode, $normalizedScores): string
    {
        $scores=(array)$normalizedScores;$sorted=$scores;arsort($sorted);$top=array_keys($sorted);
        if($testCode==='MBTI_ORG')return (($scores['E']??0)>=($scores['I']??0)?'E':'I').(($scores['S']??0)>=($scores['N']??0)?'S':'N').(($scores['T']??0)>=($scores['F']??0)?'T':'F').(($scores['J']??0)>=($scores['P']??0)?'J':'P');
        if($testCode==='DISC_ORG')return 'سبک اصلی: '.($top[0]??'-').' | سبک ثانویه: '.($top[1]??'-');
        if($testCode==='MII_ORG')return 'سه حوزه برتر: '.implode('، ',array_slice($top,0,3)).' | پیشنهاد نقش: '.(['linguistic'=>'آموزش و ارتباطات','logical_mathematical'=>'تحلیل داده و مالی','spatial'=>'انبار و چیدمان','interpersonal'=>'فروش و منابع انسانی'][$top[0]??'']??'توسعه چندمهارتی');
        if($testCode==='HOLLAND_ORG')return 'سه حوزه برتر: '.implode('، ',array_slice($top,0,3)).' | واحدهای سازگار: '.(['realistic'=>'انبار، لجستیک، عملیات میدانی','investigative'=>'برنامه‌ریزی، IT، تحلیل داده','artistic'=>'بازاریابی، محتوا، برند','social'=>'منابع انسانی، ارتباط با مشتری، آموزش','enterprising'=>'فروش، سرپرستی، مذاکره','conventional'=>'مالی، اداری، گزارش‌گیری'][$top[0]??'']??'-');
        $average=$scores?array_sum($scores)/count($scores):0;
        if($testCode==='RAVEN_ABSTRACT_ORG')return $average<40?'نیازمند تقویت':($average<60?'متوسط':($average<80?'خوب':'قوی'));
        if($testCode==='BURNOUT_ORG')return $average<40?'پایین':($average<70?'متوسط':'بالا');
        if($testCode==='JOB_SATISFACTION')return 'رضایت '.($average<40?'پایین':($average<70?'متوسط':'بالا')).' - '.number_format($average,1).' از ۱۰۰';
        if($testCode==='EQ_ORG')return 'امتیاز EQ: '.number_format($average,1).' | قوی‌ترین: '.($top[0]??'-').' | نیازمند توسعه: '.(array_key_last($sorted)??'-');
        if($testCode==='COMMITMENT_ORG')return 'پروفایل تعهد - بعد غالب: '.($top[0]??'-').' - '.number_format($average,1).' از ۱۰۰';
        if($testCode==='SPATIAL_ORG')return 'امتیاز فضایی: '.number_format($average,1).' | تناسب با انبار، مسیرچینی، مرچندایزینگ و چیدمان میدانی';
        return 'امتیاز کل: '.number_format($average,1).' از ۱۰۰';
    }

    public function getRiskLevel($testCode, $normalizedScores): string
    {
        $scores=(array)$normalizedScores;$avg=$scores?array_sum($scores)/count($scores):0;
        if($testCode==='BURNOUT_ORG')return $avg>=70?'نیازمند پیگیری':($avg>=40?'متوسط':'پایین');
        if(in_array($testCode,['JOB_SATISFACTION','COMMITMENT_ORG'],true))return $avg<40?'نیازمند پیگیری':($avg<65?'متوسط':'پایین');
        return 'پایین';
    }

    public function generateRecommendation($testCode, $normalizedScores, $employeeContext = []): string
    {
        $scores=(array)$normalizedScores;if(!$scores)return 'داده کافی برای پیشنهاد وجود ندارد.';$sorted=$scores;asort($sorted);$lowest=array_key_first($sorted);
        $special=['BURNOUT_ORG'=>'حجم کار، اولویت‌ها و امکان بازیابی انرژی در گفت‌وگوی محرمانه HR بررسی شود.','HOLLAND_ORG'=>'سه حوزه برتر با وظایف واقعی نقش فرد مقایسه شود.','DISC_ORG'=>'شیوه ارتباط مدیر با سبک غالب فرد هماهنگ شود.'];
        return $special[$testCode]??('برای توسعه حوزه «'.$lowest.'» یک اقدام کوتاه‌مدت و قابل سنجش تعیین شود.');
    }

    private function answerMax(string $type): int { return $type==='scale_1_7'?7:($type==='scale_1_5'?5:1); }
    private function profileSummary(string $code,array $scores,string $final): string { $sorted=$scores;arsort($sorted);return $final.' | برجسته‌ترین بعد: '.(array_key_first($sorted)??'-'); }
}
