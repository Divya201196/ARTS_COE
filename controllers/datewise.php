public function actionGetanswerpacketsinfo() 
    {
        //exit;
       $exam_date = date('Y-m-d', strtotime(Yii::$app->request->post('exam_date')));
        $exam_session = Yii::$app->request->post('exam_session');
        $exam_year = Yii::$app->request->post('exam_year');
        $exam_month = Yii::$app->request->post('exam_month');
       // $ans_pack_serial = strtoupper(Yii::$app->request->post('ans_pack_serial'));
        //print_r($ans_pack_serial);exit;
       // $ans_qp_code = Yii::$app->request->post('ans_qp_code');
        $batch_id = Yii::$app->request->post('batch_id');
        $packet_count = Yii::$app->request->post('ans_pack_script');
         $exam_type = Yii::$app->request->post('exam_type');

        if($packet_count<40 && $packet_count>60){ $packet_count =40;} 
       
      //  $getSessName = Categorytype::findOne($exam_session);
        $monthName = Categorytype::findOne($exam_month);
        $batchName = Batch::findOne($batch_id);
        $substr=''; $ansserail='';
        if(!empty($batchName))
        {
            $substr= substr($batchName->batch_name, -2);
            //$ansserail=$substr.$ans_pack_serial;
        }
        
        $det_disc_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%Discontinued%'")->queryScalar();


       // $subject_list=Yii::$app->db->createCommand('SELECT A.coe_subjects_mapping_id FROM coe_subjects_mapping as A JOIN coe_subjects AS B ON B.coe_subjects_id=A.subject_id WHERE B.subject_code="'.$subject_code.'"')->queryAll();

        $subject_list=Yii::$app->db->createCommand('SELECT A.qp_code,A.subject_mapping_id ,A.exam_date FROM coe_exam_timetable  as A join coe_subjects_mapping as B on B.coe_subjects_mapping_id=A.subject_mapping_id join coe_bat_deg_reg as C on C.coe_bat_deg_reg_id=B.batch_mapping_id join coe_batch as D on D.coe_batch_id=C.coe_batch_id WHERE  A.exam_month="'.$exam_month.'" AND A.exam_year="'.$exam_year.'" and A.exam_type="'.$exam_type.'" and A.exam_date="'.$exam_date.'" and A.exam_session="'.$exam_session.'"and D.coe_batch_id="'.$batch_id.'" order by exam_date  ')->queryAll();
       // print_r($subject_list);exit;
        $qp_code=[]; $mapIds=[];

        foreach ($subject_list as $sub_maps) 
        {
            $qp_code['qp_code']=$sub_maps['qp_code'];    
            $mapIds[$sub_maps['subject_mapping_id']]=$sub_maps['subject_mapping_id'];
        }  

        $qparray = array_unique($qp_code);
        //print_r($qparray); exit;
        $query_1 = new Query();
        $query_1->select(['coe_exam_timetable_id','exam_date','cover_number','qp_code','exam_year','exam_month','exam_type','exam_session'])
                    ->from('coe_exam_timetable as A')
                    ->join('JOIN','coe_subjects_mapping as B','B.coe_subjects_mapping_id=A.subject_mapping_id')
                    ->JOIN('JOIN', 'coe_bat_deg_reg C', 'C.coe_bat_deg_reg_id = B.batch_mapping_id')
                    ->where(['C.coe_batch_id' => $batch_id, 'exam_month'=>$exam_month,'exam_year'=>$exam_year,'exam_type'=>$exam_type,'exam_date'=>$exam_date,'exam_session'=>$exam_session])
                    ->orderBy('coe_exam_timetable_id ASC');
       // echo $query_1->createCommand()->getrawsql(); exit;
        $firstdata = $query_1->createCommand()->queryAll();
       // print_r( $firstdata );exit;

        $query_2 = new Query();
        $query_2->select(['coe_exam_timetable_id','cover_number'])
                    ->from('coe_exam_timetable as A')
                    ->join('JOIN','coe_subjects_mapping as B','B.coe_subjects_mapping_id=A.subject_mapping_id')
                    ->JOIN('JOIN', 'coe_bat_deg_reg C', 'C.coe_bat_deg_reg_id = B.batch_mapping_id')
                    ->where(['C.coe_batch_id' => $batch_id, 'exam_month'=>$exam_month,'exam_year'=>$exam_year,'exam_type'=>$exam_type,'exam_date'=>$exam_date,'exam_session'=>$exam_session])
                    ->orderBy('coe_exam_timetable_id ASC');
        //echo $query_2->createCommand()->getrawsql(); exit;
        $givendate = $query_2->createCommand()->queryScalar();

        $pquery='SELECT * FROM coe_exam_timetable WHERE coe_exam_timetable_id < "'.$givendate.'" and exam_month="'.$exam_month.'" AND exam_year="'.$exam_year.'" ORDER BY coe_exam_timetable_id DESC LIMIT 1';
        $prev_examAllDet=Yii::$app->db->createCommand($pquery)->queryone();

        $pre_data = $firstd_data=0; $anspac_data =0;
        $prev_date = date('Y-m-d', strtotime($prev_examAllDet['exam_date']));
        foreach($firstdata as $value)
        {

            $ans_pack_query_all = new Query();
            $ans_pack_query_all->select(['A.*'])
                            ->from('coe_answer_packet as A')
                            ->where(['A.exam_date' => $value['exam_date'],'A.exam_month'=>$value['exam_month'],'A.exam_year'=>$value['exam_year'],'exam_type'=>$value['exam_type'],'exam_session'=>$value['exam_session']])
                            ->groupBy(['subject_code']); 
            //echo $ans_pack_query_all->createCommand()->getrawsql(); exit;
            $ans_pack_dta_all = $ans_pack_query_all->createCommand()->queryAll();
            $anspac_data = count($ans_pack_dta_all);
    
        
            $firstq = new Query();
            $firstq->select(['A.*'])
                            ->from('coe_answer_packet as A')
                            ->where(['A.exam_date' => $value['exam_date'],'A.exam_month'=>$value['exam_month'],'A.exam_year'=>$value['exam_year'],'exam_type'=>$value['exam_type'],'exam_session'=>$value['exam_session']]); 
            $firstd = $firstq->createCommand()->queryAll();
            $firstd_data = count($firstd);
        

         require(Yii::getAlias('@webroot/includes/use_institute_info.php'));
                $body = $header ='';
                $total_script=0;
                $scriptNumber = 0;
                $previ_print='';
                        
                $header .= '<table border=1 width="100%"  style="overflow-x:auto;"  cellspacing="0" cellpadding="0" border="0" id="student_bulk_edit"  >
                        <tr>
                            <td>
                                <img class="img-responsive" width="100" height="100" src="' . Yii::getAlias("@web") . '/images/main_logo.png" alt="College Logo">
                            </td>
                            <td colspan=5 align="center"><h3> 
                                  <center><b><font size="5px">' . $org_name . '</font></b></center>
                                  <center> <font size="3px">' . $org_address . '</font></center>
                                  <center class="tag_line"><b>' . $org_tagline . '</b></center> 
                                  </h3>
                             </td>
                              <td align="center">  
                                <img width="100" height="100" class="img-responsive" src="' . Yii::getAlias("@web") . '/images/skacas.png" alt="College Logo 2">
                              </td>
                         </tr>';
                        
                        if(Yii::$app->request->post('exam_date')=='')
                        {
                             $header .= '<tr>
                            <td align="center" colspan=7 ><h4>  REGULAR / ARREAR EXAMNIATIONS ANSWER PACKET   </h4> </td>
                                </tr>';
                        }
                        else
                        {
                             $header .= '<tr>
                            <td align="center" colspan=7 ><h4>  REGULAR / ARREAR EXAMNIATIONS ANSWER PACKET FOR DATE : <b>'.date('d-m-Y',strtotime($value['exam_date'])).'</b> SESSION : <b>'.strtoupper($getSessName->description).'</b>   </h4>
                            </td> </tr>';
                        }
                         

                $header .= '<tr>
                                <th>EXAM DATE </th>
                                <th>EXAM SESSION </th>
                                <th>QP CODE</th>
                                
                                <th>ANSWER COVER NUMBER ALLOTED</th>
                                <th>NO. OF REGISTERED</th>
                                 <th>NO. OF ABSENTEES</th>
                                 <th>NO. OF SCRIPTS IN THE COVER</th>
                            </tr>                     
                         '; 
        //ECHO $firstdata['coe_exam_timetable_id']."==".$givendate;exit();
        if($batch_id!='')
        {
            
                $ans_pack_query_all = new Query();

               
                     $ans_pack_query_all->select(['A.*'])
                                ->from('coe_answer_packet as A')
                                ->where(['A.exam_date' => $value['exam_date'], 'A.exam_type'=>$value['exam_type'],'A.exam_month'=>$value['exam_month'],'A.exam_year'=>$value['exam_year'],'A.qp_code'=>$value['qp_code']])->orderBy('A.answer_packet_number');
                
                //echo $ans_pack_query_all->createCommand()->getrawsql(); exit;
                $ans_pack_dta_all = $ans_pack_query_all->createCommand()->queryAll();
                 $substr= substr($batchName->batch_name, -2);
                 $ansserail=$substr.$value['cover_number'];
               // print_r($ans_pack_dta_all);exit;

                if(empty($ans_pack_dta_all))
                {
                    
                    $lp=0;
                    foreach ($qparray as $sub_maps) 
                    {              

                        if($lp==0)
                        {
                            $ans_pack_query = new Query();
                            $ans_pack_query->select(['answer_packet_number','coe_batch_id'])
                                            ->from('coe_answer_packet')
                                            ->where(['answer_packet_serial' => $ansserail,'coe_batch_id' => $batch_id,'exam_month'=>$exam_month,'exam_year'=>$exam_year,'exam_type'=>$value['exam_type']])->orderBy('answer_packet_id DESC'); 
                            //echo $ans_pack_query->createCommand()->getrawsql(); exit;
                            $ans_pack_dta = $ans_pack_query->createCommand()->queryOne();

                            $ans_packet_no=0;

                            if(!empty($ans_pack_dta))
                            {
                                $ans_packet_no= $ans_pack_dta['answer_packet_number']+1;
                            }
                            else
                            {
                                $ans_packet_no=1;
                            }
                        }
                        else
                        {
                            $ans_pack_query = new Query();
                            $ans_pack_query->select(['answer_packet_number','coe_batch_id'])
                                            ->from('coe_answer_packet')
                                            ->where(['answer_packet_serial' => $ansserail,'coe_batch_id' => $batch_id,'exam_month'=>$exam_month,'exam_year'=>$exam_year,'exam_type'=>$value['exam_type']])->orderBy('answer_packet_id DESC'); 
                            //echo $ans_pack_query->createCommand()->getrawsql(); exit;
                            $ans_pack_dta = $ans_pack_query->createCommand()->queryOne();

                            $ans_packet_no=0;

                            if(!empty($ans_pack_dta))
                            {
                                $ans_packet_no= $ans_pack_dta['answer_packet_number']+1;
                            }
                            else
                            {
                                $ans_packet_no=1;
                            }
                             
                        }
                            $disp = $ans_packet_no;

                        $mapid=Yii::$app->db->createCommand('SELECT subject_mapping_id FROM coe_exam_timetable WHERE qp_code="'.$sub_maps.'" AND exam_month="'.$exam_month.'" AND exam_year="'.$exam_year.'" AND exam_type="'.$value['exam_type'].'"')->queryScalar(); 

                        $getSubInfoDe=Yii::$app->db->createCommand('SELECT B.* FROM coe_subjects_mapping A JOIN coe_subjects B ON B.coe_subjects_id=A.subject_id WHERE A.coe_subjects_mapping_id="'.$mapid.'"')->queryone();                  
                   
                        $query = new Query();
                        $query->select(['A.*'])
                                        ->from('coe_absent_entry as A')
                                        ->where(['A.exam_date' => $value['exam_date'], 'A.exam_session' => $value['exam_session'],'exam_month'=>$exam_month,'exam_year'=>$exam_year,'A.exam_type'=>$exam_type])->andWhere(['IN','A.exam_subject_id',$mapIds]);
                        $total_absent = $query->createCommand()->queryAll();

                        $query_1 = new Query();
                        $query_1->select(['count(*) as present'])
                            ->from('coe_hall_allocate as A')
                            ->JOIN('JOIN', 'coe_exam_timetable B', 'B.coe_exam_timetable_id = A.exam_timetable_id')
                            ->join('JOIN','coe_student as C','C.register_number=A.register_number ')
                            ->join('JOIN','coe_student_mapping as D','D.student_rel_id=C.coe_student_id ')
                            ->where(['B.exam_date' => $value['exam_date'], 'B.exam_session' => $value['exam_session'],'exam_month'=>$exam_month,'exam_year'=>$exam_year,'exam_type'=>$exam_type])
                            ->andWhere(['IN','qp_code',$sub_maps])
                            ->andWhere(['<>','status_category_type_id',$det_disc_type]);
                        echo $total_present = $query_1->createCommand()->getrawsql();exit;
                       // echo $total_present->createCommand()->getrawsql(); exit;                    
                                    
                        $present_students =($total_present);
                         // ($total_present-count($total_absent));


                        $inc_script = $total_present==0 || empty($total_present) || $present_students==0 ?0:1;
                        $scriptNumber = $scriptNumber+$inc_script;
                        $print_scripts_number = strlen($scriptNumber)==1?'0'.$scriptNumber:$scriptNumber;

                        $ScriptsCunt = $present_students==0 || $disp=='-' ?'-': (strlen($present_students)==1?"0".$present_students:$present_students);
                                   
                        if($ScriptsCunt>$packet_count)
                        {
                            $break_val = ceil($ScriptsCunt/$packet_count);
                            $total_script_break = $ScriptsCunt;
                            $ans_packs = $les_make=$packet_count; 
                            for ($break=0; $break <$break_val ; $break++) 
                            { 
                                $ans_pack_serial1='';

                                if($break==0)
                                {
                                    if($ans_packs>0)
                                    {
                                        $ans_pack_serial1=$substr.$ans_pack_serial;
                                        Yii::$app->db->createCommand('INSERT into coe_answer_packet(exam_year,exam_month,exam_date,exam_session,subject_code,subject_name,qp_code,answer_packet_number,total_answer_scripts,print_script_count,answer_packet_serial,created_at, coe_batch_id) values("'.$exam_year.'","'.$exam_month.'","'.date('Y-m-d',strtotime($exam_date)).'","'.$getSessName->description.'","'.$getSubInfoDe['subject_code'].'","'.$getSubInfoDe['subject_name'].'","'.$ans_qp_code.'","'.$disp.'","'.$ans_packs.'","'.$packet_count.'","'.$ans_pack_serial1.'","'.date('Y-m-d H:i:s').'","'.$batch_id.'") ')->execute();

                                        $body .='<tr>';
                                        $body .='<td>'.date('d-m-Y',strtotime($exam_date)).'</td>';
                                        $body .='<td>'.strtoupper($getSessName->description).'</td>';
                                        $body .='<td>'.$ans_qp_code.'</td>';
                                       // $body .='<td>'.strtoupper($getSubInfoDe['subject_name']).'</td>'; 
                                        $body .='<td>'.$ans_pack_serial1.$disp.'</td>';
                                        $body .='<td>'.$ans_packs.'</td><td> </td><td> </td>';
                                        $body .='</tr>';

                                        $total_script=$total_script+$ans_packs;
                                    }

                                }
                                else if($break==($break_val-1))
                                {
                                    $les_make = $les_make-$packet_count;
                                    $print = $ScriptsCunt-$les_make; 
                                    if($print>0)
                                    { 
                                        $ans_pack_serial1=$substr.$ans_pack_serial;

                                        Yii::$app->db->createCommand('INSERT into coe_answer_packet(exam_year,exam_month,exam_date,exam_session,subject_code,subject_name,qp_code,answer_packet_number,total_answer_scripts,print_script_count,answer_packet_serial,created_at, coe_batch_id) values("'.$exam_year.'","'.$exam_month.'","'.date('Y-m-d',strtotime($exam_date)).'","'.$getSessName->description.'","'.$getSubInfoDe['subject_code'].'","'.$getSubInfoDe['subject_name'].'","'.$ans_qp_code.'","'.$disp.'","'.$print.'","'.$packet_count.'","'.$ans_pack_serial1.'","'.date('Y-m-d H:i:s').'","'.$batch_id.'") ')->execute();                                                                                    
                                        $body .='<tr>';
                                        $body .='<td align="center">,,</td>';
                                        $body .='<td align="center">,,</td>';
                                        $body .='<td>'.$ans_qp_code.'</td>';
                                        //$body .='<td>'.strtoupper($getSubInfoDe['subject_name']).'</td>'; 
                                        $body .='<td>'.$ans_pack_serial1.$disp.'</td>';
                                        $body .='<td>'.$print.'</td><td> </td><td> </td>';
                                        $body .='</tr>'; 

                                        $total_script=$total_script+$print;
                                        break;
                                    }
                                }
                                else
                                {
                                    if($ans_packs>0)
                                    { 
                                        $print = $ans_packs;
                                        $ans_pack_serial1=$substr.$ans_pack_serial;
                                        Yii::$app->db->createCommand('INSERT into coe_answer_packet(exam_year,exam_month,exam_date,exam_session,subject_code,subject_name,qp_code,answer_packet_number,total_answer_scripts,print_script_count,answer_packet_serial,created_at, coe_batch_id) values("'.$exam_year.'","'.$exam_month.'","'.date('Y-m-d',strtotime($exam_date)).'","'.$getSessName->description.'","'.$getSubInfoDe['subject_code'].'","'.$getSubInfoDe['subject_name'].'","'.$ans_qp_code.'","'.$disp.'","'.$print.'","'.$packet_count.'","'.$ans_pack_serial1.'","'.date('Y-m-d H:i:s').'","'.$batch_id.'") ')->execute();

                                        $print = $ans_packs;
                                        $body .='<tr>';
                                        $body .='<td align="center">,,</td>';
                                        $body .='<td align="center">,,</td>';
                                        $body .='<td>'.$ans_qp_code.'</td>';
                                       // $body .='<td>'.strtoupper($getSubInfoDe['subject_name']).'</td>'; 
                                        $body .='<td>'.$ans_pack_serial1.$disp.'</td>';
                                        $body .='<td>'.$ans_packs.'</td><td> </td><td> </td>';
                                        $body .='</tr>';

                                        $total_script=$total_script+$ans_packs;
                                    }

                                }
                                $les_make = $les_make+$packet_count; 
                                $scriptNumber++;
                                $disp++;
                                $previ_print = $scriptNumber;
                            }

                                       
                        }
                        else
                        {
                            if($ScriptsCunt>0)
                            {   
                                $ans_pack_serial1=$substr.$ans_pack_serial;
                                
                                Yii::$app->db->createCommand('INSERT into coe_answer_packet(exam_year,exam_month,exam_date,exam_session,subject_code,subject_name,qp_code,answer_packet_number,total_answer_scripts,print_script_count,answer_packet_serial,created_at, coe_batch_id) values("'.$exam_year.'","'.$exam_month.'","'.date('Y-m-d',strtotime($exam_date)).'","'.$getSessName->description.'","'.$getSubInfoDe['subject_code'].'","'.$getSubInfoDe['subject_name'].'","'.$ans_qp_code.'","'.$disp.'","'.$ScriptsCunt.'","'.$packet_count.'","'.$ans_pack_serial1.'","'.date('Y-m-d H:i:s').'","'.$batch_id.'") ')->execute();
                                       
                                $body .='<tr>';
                                $body .='<td>'.date('d-m-Y',strtotime($exam_date)).'</td>';
                                $body .='<td>'.strtoupper($getSessName->description).'</td>';
                                $body .='<td>'.$ans_qp_code.'</td>';
                                //$body .='<td>'.strtoupper($getSubInfoDe['subject_name']).'</td>'; 
                                $body .='<td>'.$ans_pack_serial1.$disp.'</td>';
                                $body .='<td>'.$ScriptsCunt.'</td><td> </td><td> </td>';
                                $body .='</tr>';

                                $total_script=$total_script+$ScriptsCunt;
                            }
                            
                        }

                       $lp++; 
                    }
                    
                    $body.='<tr><td colspan="4" align="right">Total Script: </td><td>'.$total_script.'</td><td> </td><td> </td></tr>';
                    $send_results = $header.$body."</table>";
                }
                else //view scripts
                {
                    //echo "ji"; exit;
                    if(!empty($ans_pack_dta_all))
                    {
                       
                        $total_absent=$total_nos= $total_script=0; $tmp_sub=''; $anspacket=''; $i=1; $pc1=0; $complete=0;
                        foreach($ans_pack_dta_all as $anspackdata)
                        {
                            $pno=$anspackdata['answer_packet_serial'].$anspackdata['answer_packet_number'];

                            $check_pno = Yii::$app->db->createCommand("select count(stu_reg_no) from coe_abanswerpack_regno where answer_packet_number ='".$pno."'  AND exam_date='".$anspackdata['exam_date']."' AND exam_session='".$getSessName->description."' ")->queryScalar();  

                             $nos=$anspackdata['total_answer_scripts']-$check_pno;

                                $body .='<tr>';
                                $body .='<td>'.date('d-m-Y',strtotime($anspackdata['exam_date'])).'</td>';
                                $body .='<td>'.strtoupper($anspackdata['exam_session']).'</td>';
                                $body .='<td>'.strtoupper($anspackdata['qp_code']).'</td>';
                               // $body .='<td>'.strtoupper($anspackdata['subject_name']).'</td>'; 
                                $body .='<td>'.$anspackdata['answer_packet_serial'].$anspackdata['answer_packet_number'].'</td>';

                               
                                $body .='<td>'.$anspackdata['total_answer_scripts'].'</td><td>'.$check_pno.'</td><td>'.$nos.'</td>';

                               
                                $body .='</tr>';

                                $total_absent=$total_absent+$check_pno;
                                $total_nos= $total_nos+ $nos;                             
                                $total_script=$total_script+$anspackdata['total_answer_scripts'];
                        $i++;
                        }
                        $body.='<tr><td colspan="4" align="right">Total Script: </td><td>'.$total_script.'</td><td>'.$total_absent.'</td><td>'.$total_nos.'</td></tr>';
                        $send_results = $header.$body."</table>";
                    }
                    else 
                    {
                        $send_results = 0;
                    }
                }
            
           
        }
        else
        {
            $ans_pack_query_all = new Query();
            if($batch_id!='' && $ans_pack_serial!='' && Yii::$app->request->post('exam_date')=='' && Yii::$app->request->post('exam_session')=='' && $ans_qp_code=='')
            {
                $ansseries=$substr.$ans_pack_serial;
                //print_r( $ans_pack_serial);exit;
                $ans_pack_query_all->select(['A.*'])
                            ->from('coe_answer_packet as A')
                            ->where(['answer_packet_serial' =>$ansseries,'coe_batch_id' => $batch_id,'A.exam_month'=>$exam_month,'A.exam_year'=>$exam_year]);
            }
            else if($batch_id!='' && Yii::$app->request->post('exam_date')=='' && Yii::$app->request->post('exam_session')=='' && $ans_qp_code=='')
            {
                $ans_pack_query_all->select(['A.*'])
                            ->from('coe_answer_packet as A')
                            ->where(['coe_batch_id' => $batch_id,'A.exam_month'=>$exam_month,'A.exam_year'=>$exam_year]);
            }
            else if($batch_id!='' && Yii::$app->request->post('exam_date')!='' && Yii::$app->request->post('exam_session')=='' && $ans_qp_code=='')
            {
                $ans_pack_query_all->select(['A.*'])
                                ->from('coe_answer_packet as A')
                                ->where(['coe_batch_id' => $batch_id,'A.exam_date' => $exam_date, 'A.exam_month'=>$exam_month,'A.exam_year'=>$exam_year]);
            }
            else if($batch_id!='' && Yii::$app->request->post('exam_date')!='' && Yii::$app->request->post('exam_session')!='' && $ans_qp_code=='')
            {
                $ans_pack_query_all->select(['A.*'])
                                ->from('coe_answer_packet as A')
                                ->where(['coe_batch_id' => $batch_id,'A.exam_date' => $exam_date, 'A.exam_session' => $getSessName->category_type,'A.exam_month'=>$exam_month,'A.exam_year'=>$exam_year]);
            }
            else if($batch_id!='' && Yii::$app->request->post('exam_date')!='' && Yii::$app->request->post('exam_session')!='' && $ans_qp_code!='')
            {
                $ans_pack_query_all->select(['A.*'])
                                ->from('coe_answer_packet as A')
                                ->where(['qp_code' => $ans_qp_code,'coe_batch_id' => $batch_id,'A.exam_date' => $exam_date, 'A.exam_session' => $getSessName->category_type,'A.exam_month'=>$exam_month,'A.exam_year'=>$exam_year]);
            }
            else
            {
                $ans_pack_query_all->select(['A.*'])
                            ->from('coe_answer_packet as A')
                            ->where(['A.exam_month'=>$exam_month,'A.exam_year'=>$exam_year]);
            }

            $ans_pack_dta_all = $ans_pack_query_all->createCommand()->queryAll();

             if(!empty($ans_pack_dta_all))
            {
                       
                $total_absent=$total_nos= $total_script=0; $tmp_sub=''; $anspacket=''; $i=1; $pc1=0; $complete=0;
                foreach($ans_pack_dta_all as $anspackdata)
                {
                    
                    $pno=$anspackdata['answer_packet_serial'].$anspackdata['answer_packet_number'];

                    $check_pno = Yii::$app->db->createCommand("select count(stu_reg_no) from coe_abanswerpack_regno where answer_packet_number ='".$pno."'  AND exam_date='".$anspackdata['exam_date']."' AND exam_session='".$anspackdata['exam_session']."' ")->queryScalar();  

                     $nos=$anspackdata['total_answer_scripts']-$check_pno;                     

                    $body .='<tr>';
                    $body .='<td>'.date('d-m-Y',strtotime($anspackdata['exam_date'])).'</td>';
                    $body .='<td>'.strtoupper($anspackdata['exam_session']).'</td>';
                    $body .='<td>'.strtoupper($anspackdata['qp_code']).'</td>';
                   // $body .='<td>'.strtoupper($anspackdata['subject_name']).'</td>'; 
                    $body .='<td>'.$anspackdata['answer_packet_serial'].$anspackdata['answer_packet_number'].'</td>';
                    $body .='<td>'.$anspackdata['total_answer_scripts'].'</td><td>'.$check_pno.'</td><td>'.$nos.'</td>';
                    $body .='</tr>';

                    $total_absent=$total_absent+$check_pno;
                    $total_nos= $total_nos+ $nos; 

                    $total_script=$total_script+$anspackdata['total_answer_scripts'];
                    $i++;
                }
                $body.='<tr><td colspan="4" align="right">Total Script: </td><td>'.$total_script.'</td><td>'.$total_absent.'</td><td>'.$total_nos.'</td></tr>';
                $send_results = $header.$body."</table>";
            }
        
            else 
            {
                $send_results = 0;
            }
        }
    }
        if (isset($_SESSION['get_answer_packet'])) {
            unset($_SESSION['get_answer_packet']);
            
        }
        $_SESSION['get_answer_packet'] = $send_results;
        return Json::encode($send_results);
}*/