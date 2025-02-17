public function actionDummyNumberEntrynew()
    {

        $model = new DummyNumbers();
        $examtimetable = new ExamTimetable();
        $connection = Yii::$app->db;
        $factallModel = new ValuationFacultyAllocate();
        $verify_stu_data='';  $_SESSION['get_print_dummy_mark'] ='';


        if(Yii::$app->request->post())
        {  
            $totalSuccess=0;
            $exam_year = $_POST['dummynumber_exam_year'];
                    
            $exam_month = $_POST['dummynumber_exam_month'];            

            $qp_code=$_POST['dummynumber_val_faculty_id'];
            //print_r($qp_code);exit;

            // $val_facl_id=explode('-',$val_facl_id);

            // $val_fac_all_id= $val_facl_id[0];

            // $exam_subject_code= $val_facl_id[1];

            // $pack_no=$val_facl_id[2];

            // $val_fac_allcoate = ValuationFacultyAllocate::find()->where(['val_faculty_all_id'=>$val_fac_all_id])->one();

            // $val_date = $val_fac_allcoate['valuation_date'];

            // $val_fac_name = ValuationFaculty::find()->where(['coe_val_faculty_id'=>$val_fac_allcoate['coe_val_faculty_id']])->one();
            
            // $examiner_name = $val_fac_name['faculty_name'];

            $created_at = date("Y-m-d H:i:s");
            $updateBy = Yii::$app->user->getId(); 

            $externAl = Categorytype::find()->where(['category_type'=>'ESE'])->one();
            $category_type_id = Categorytype::find()->where(['description'=>"ESE(Dummy)"])->one();
            $cia_type_id = Categorytype::find()->where(['description'=>"CIA"])->orWhere(['description'=>'Internal'])->one();
            $reg_cat_id = Categorytype::find()->where(['description'=>"Regular"])->one();
            $arr_cat_id = Categorytype::find()->where(['description'=>"Arrear"])->one();
            $exam_term_id = Categorytype::find()->where(['description'=>"End"])->one();
            $exam_term = $exam_term_id->coe_category_type_id;
            $det_disc_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%Discontinued%'")->queryScalar();

            //  $val_fac_allcoate = Yii::$app->db->createCommand("SELECT * FROM coe_valuation_faculty_allocate WHERE  val_faculty_all_id='" . $val_facl_id[0] . "'")->queryone();

            //  $sub_map = Yii::$app->db->createCommand("SELECT subject_mapping_id as subject_map_id FROM coe_answerpack_regno WHERE exam_year='" . $exam_year . "' AND exam_month='" . $exam_month . "' AND answer_packet_number='" . $val_fac_allcoate['subject_pack_i'] . "' group By subject_mapping_id ")->queryAll(); 

            // $submapid='';

            // foreach ($sub_map as $value) {
            //      $submapid.=$value['subject_map_id'].',';
            // }
            // $submapid='('.rtrim($submapid,',').')';

          //   $verify_stu_data = Yii::$app->db->createCommand("SELECT * FROM coe_valuation_mark_details A JOIN coe_student B ON B.register_number=A.stu_reg_no JOIN coe_student_mapping C ON C.student_rel_id=B.coe_student_id JOIN coe_answerpack_regno D ON D.stu_reg_no=A.stu_reg_no JOIN coe_mark_entry_master E ON E.student_map_id=C.coe_student_mapping_id AND E.subject_map_id=D.subject_mapping_id JOIN coe_subjects_mapping F ON F.coe_subjects_mapping_id=D.subject_mapping_id JOIN coe_subjects G ON G.coe_subjects_id=F.subject_id WHERE A.val_faculty_all_id='" . $val_facl_id[0] . "' AND E.subject_map_id IN ".$submapid."  ")->queryAll();

            
            
           // $check_verify_count = Yii::$app->db->createCommand("SELECT count(*) FROM coe_valuation_mark_details WHERE val_faculty_all_id='" . $val_facl_id[0] . "' ")->queryScalar(); 

            //echo  count($verify_stu_data)."<br>".$check_verify_count; exit;
           // print_r($_POST['register_numbers']);exit;
                 //new mark entered save to mark entry table
            if( isset($_POST['register_numbers'])) 
            {
                $get_id_details_data = ['exam_year'=>$exam_year,'exam_month'=>$exam_month];

                for ($i=0; $i <count($_POST['register_numbers']) ; $i++) 
                {
                   echo $_POST['register_numbers'][$i];
                    $subject_map_id_dum = $_POST['sub_map_id'][$i];
                    $stu_cia_marks_check = MarkEntry::find()->where(['student_map_id'=>$_POST['register_numbers'][$i],'category_type_id'=>$cia_type_id->coe_category_type_id,'subject_map_id'=>$subject_map_id_dum])->one();
                   //print_r( $stu_cia_marks_check);exit;
                    if(empty($stu_cia_marks_check))
                    {
                        $stu_cia_marks_check_master = MarkEntryMaster::find()->where(['student_map_id'=>$_POST['register_numbers'][$i],'subject_map_id'=>$subject_map_id_dum])->orderBy('coe_mark_entry_master_id asc')->one();
                        if(empty($stu_cia_marks_check_master))
                        {
                            Yii::$app->ShowFlashMessages->setMsg('Error','No Data Found!! Kindly Fininsh the Internal Mark Entry First.');
                            return $this->redirect(['dummy-numbers/dummy-number-entrynew']);   
                        }
                        else
                        {
                             $stu_cia_marks_check['category_type_id_marks'] = $stu_cia_marks_check_master['CIA'];
                        }
                    }


                    $stu_cia_marks = MarkEntry::find()->where(['student_map_id'=>$_POST['register_numbers'][$i],'category_type_id'=>$category_type_id->coe_category_type_id,'year'=>$get_id_details_data['exam_year'],'month'=>$get_id_details_data['exam_month'],'subject_map_id'=>$subject_map_id_dum])->all();

                    $stu_ese_marks = MarkEntryMaster::find()->where(['student_map_id'=>$_POST['register_numbers'][$i],'year'=>$get_id_details_data['exam_year'],'month'=>$get_id_details_data['exam_month'],'subject_map_id'=>$subject_map_id_dum])->all();
                    //print_r($stu_ese_marks);exit;

                    if(empty($stu_cia_marks) && empty($stu_ese_marks))
                    {
                        $mark_entry_master = new MarkEntryMaster();
                        $mark_entry = new MarkEntry();
                        $regulation = new Regulation();
                        $student = StudentMapping::findOne($_POST['register_numbers'][$i]);
                        $checkMarkEntry = MarkEntryMaster::find()->where(['student_map_id'=>$_POST['register_numbers'][$i],'subject_map_id'=>$subject_map_id_dum])->one();
                        $sub_map_ids = $subject_map_id_dum;
                        $exam_type = empty($checkMarkEntry)?$reg_cat_id->coe_category_type_id:$arr_cat_id->coe_category_type_id;
                
                        if(!empty($stu_cia_marks_check))
                        {
                            $stu_reg=StuInfo::findOne(['stu_map_id'=>$_POST['register_numbers'][$i]]);
                           
                            if($_POST['ese_marks'][$i]!='-1' && $_POST['ese_marks'][$i]>='0')
                            {                                

                                $getbatch=Yii::$app->db->createCommand("SELECT A.batch_name,A.coe_batch_id FROM coe_batch A JOIN coe_bat_deg_reg B ON B.coe_batch_id=A.coe_batch_id JOIN coe_student_mapping C ON C.course_batch_mapping_id=B.coe_bat_deg_reg_id WHERE B.coe_bat_deg_reg_id='".$stu_reg['batch_map_id']."'")->queryone();

                                $batch_name= $getbatch['batch_name'];

                               // $INSERT_ESE_MARKS = $_POST['ese_marks'][$i]=='-1'?0:$_POST['ese_marks'][$i];
                                $CIA=$stu_cia_marks_check['category_type_id_marks'];

                                $subese_marks= Yii::$app->db->createCommand("SELECT * FROM coe_subjects A JOIN coe_subjects_mapping B ON B.subject_id=A.coe_subjects_id WHERE B.coe_subjects_mapping_id='" . $subject_map_id_dum . "' ")->queryOne();
                                $stu_result_data=array();
                                if($subese_marks['ESE_max']==25 ||$subese_marks['ESE_max']==30)
                                {  
                                    $convert_ese_marks = round((($_POST['ese_marks'][$i])/2),0);
                                    //print_r($convert_ese_marks);exit;
                                     $INSERT_ESE_MARKS = $convert_ese_marks;
                                     $cia_marks=$CIA;
                                   $final_sub_total = $subese_marks['ESE_max']+$subese_marks['CIA_max'];
                                      // $total_marks = $convert_ese_marks+$cia_marks;


                                     $insert_total=$convert_ese_marks+$cia_marks;

                                     $total_marks = round(($insert_total/$final_sub_total)*10,1);//exit;

                                     $total_marks=$total_marks*10;
                                      $regulation = CoeBatDegReg::find()->where(['coe_batch_id'=>$getbatch['coe_batch_id'],'coe_bat_deg_reg_id'=>$subese_marks['batch_mapping_id']])->one();

        
                                      $grade_details = Regulation::find()->where(['regulation_year'=>$regulation->regulation_year])->all();
                                      $get_sub_max = $subject_details = Subjects::findOne($subese_marks['coe_subjects_id']);
                                      $config_attempt = ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_CIA_ZEO);
                                        $check_attempt = Yii::$app->db->createCommand('SELECT count(*) FROM coe_mark_entry_master WHERE subject_map_id="' . $subject_map_id_dum . '" AND student_map_id="' .$_POST['register_numbers'][$i] . '" AND result not like "%pass%" ')->queryScalar();
                                        $attempt = isset($check_attempt) && $check_attempt!="" ? (count($check_attempt)+1) : 0;


                                     foreach ($grade_details as $value) 
                                      {


                                          if($value['grade_point_to']!='')
                                          {
                                             
                                              //echo $insert_total;//exit;

                                              if($total_marks >= $value['grade_point_from'] &&  $total_marks <= $value['grade_point_to'] )
                                              {
                                                  if( $subject_details->CIA_max!=0 && ( $cia_marks<$subject_details->CIA_min || $convert_ese_marks<$subject_details->ESE_min || $insert_total<$subject_details->total_minimum_pass ) )
                                                  {
                                                    $result_stu = 'Fail';
                                                    $stu_result_data = ['result'=>$result_stu,'total_marks'=>$insert_total,'grade_name'=>'U','grade_point'=>0,'attempt'=>$attempt,'year_of_passing'=>'','ese_marks'=>$convert_ese_marks];        
                                                  } 
                                                  else if($subject_details->CIA_max==0 && ( $convert_ese_marks<$subject_details->ESE_min || $insert_total<$subject_details->total_minimum_pass ) )
                                                  {
                                                    $result_stu = 'Fail';
                                                    $stu_result_data = ['result'=>$result_stu,'total_marks'=>$insert_total,'grade_name'=>'U','grade_point'=>0,'attempt'=>$attempt,'year_of_passing'=>'','ese_marks'=>$convert_ese_marks];        
                                                  }      
                                                  else
                                                  {
                                                    $grade_name_prit = $value['grade_name'];
                                                    
                                                    $grade_point_arts = round(($insert_total/($get_sub_max->ESE_max+$get_sub_max->CIA_max) *10),1) ;
                                                  if($subject_details['CIA_max']==0)
                                                  {
                                                    $grade_point_arts = round(( (0+$convert_ese_marks)/$final_sub_total)*10,1);
                                                  }

                                                    $stu_result_data = ['result'=>'Pass','total_marks'=>$insert_total,'grade_name'=>$grade_name_prit,'grade_point'=>$grade_point_arts,'attempt'=>$attempt,'ese_marks'=>$convert_ese_marks,'year_of_passing'=>$get_id_details_data['exam_month']."-".$get_id_details_data['exam_year']];

                                                   }


                        
                                                  
                                              }
                                          } 
                                          // Not Empty of the Grade Point 


                                      }

                                }
                                else
                                {

                                     $INSERT_ESE_MARKS = $_POST['ese_marks'][$i]=='-1'?0:$_POST['ese_marks'][$i];
                                    $stu_result_data = ConfigUtilities::StudentResult($_POST['register_numbers'][$i], $subject_map_id_dum, $CIA, $INSERT_ESE_MARKS,$get_id_details_data['exam_year'],$get_id_details_data['exam_month']);
                                }
                                //print_r($stu_result_data);exit; 

                                $model_save = new MarkEntry();
                                $model_save->student_map_id = $_POST['register_numbers'][$i];
                                $model_save->subject_map_id = $subject_map_id_dum;
                                $model_save->category_type_id =$externAl->coe_category_type_id;
                                $model_save->category_type_id_marks =$INSERT_ESE_MARKS;
                                $model_save->year = $get_id_details_data['exam_year'];
                                $model_save->month = $get_id_details_data['exam_month'];
                                $model_save->term = $exam_term;
                                $model_save->mark_type = $exam_type;
                                $model_save->created_at = $created_at;
                                $model_save->created_by = $updateBy;
                                $model_save->updated_at = $created_at;
                                $model_save->updated_by = $updateBy;

                                if($model_save->save(false))
                                {
                                    $year_of_passing = $stu_result_data['result'] == "Pass" || $stu_result_data['result'] == "pass" || $stu_result_data['result'] == "PASS" ? $get_id_details_data['exam_month']. "-" . $get_id_details_data['exam_year'] : '';
                                    $res_update = $_POST['ese_marks'][$i]=='-1'?'Absent':$stu_result_data['result'];
                                    $grade_name = $_POST['ese_marks'][$i]=='-1'?'AB':$stu_result_data['grade_name'];

                                    $markentrymaster = new MarkEntryMaster();
                                    $markentrymaster->student_map_id = $_POST['register_numbers'][$i];
                                    $markentrymaster->subject_map_id =$subject_map_id_dum;
                                    $markentrymaster->CIA = $CIA;
                                    $markentrymaster->ESE = $stu_result_data['ese_marks'];
                                    $markentrymaster->total = $stu_result_data['total_marks'];
                                    $markentrymaster->result = $res_update;
                                    $markentrymaster->grade_point = $stu_result_data['grade_point'];
                                    $markentrymaster->grade_name = $grade_name;
                                    $markentrymaster->attempt = $stu_result_data['attempt'];
                                    $markentrymaster->year = $get_id_details_data['exam_year'];
                                    $markentrymaster->month = $get_id_details_data['exam_month'];
                                    $markentrymaster->term = $exam_term;
                                    $markentrymaster->mark_type = $exam_type;
                                    $markentrymaster->year_of_passing = $year_of_passing;
                                    $markentrymaster->status_id = 0;
                                    $markentrymaster->created_by = $updateBy;
                                    $markentrymaster->created_at = $created_at;
                                    $markentrymaster->updated_by = $updateBy;
                                    $markentrymaster->updated_at = $created_at;
                                 
                                    if($markentrymaster->save(false))
                                    {
                                        try
                                        {
                                            
                                            $totalSuccess+=1;
                                        }
                                        catch(\Exception $e)
                                        {

                                           $dispResults[] = ['type' => 'E',  'message' => $message];
                                        }
                                        
                                        $dispResults[] = ['type' => 'S',  'message' => 'Success']; 
                                    }
                                    else
                                    {
                                        $dispResults[] = ['type' => 'E',  'message' => 'Error'];
                                    }
                                }
                            }
                            else
                            {
                               $getbatch=Yii::$app->db->createCommand("SELECT A.batch_name FROM coe_batch A JOIN coe_bat_deg_reg B ON B.coe_batch_id=A.coe_batch_id JOIN coe_student_mapping C ON C.course_batch_mapping_id=B.coe_bat_deg_reg_id WHERE B.coe_bat_deg_reg_id='".$stu_reg['batch_map_id']."'")->queryone();

                                $batch_name= $getbatch['batch_name'];

                                $INSERT_ESE_MARKS = $_POST['ese_marks'][$i]=='-1'?0:$_POST['ese_marks'][$i];
                                $CIA=$stu_cia_marks_check['category_type_id_marks'];
                                $stu_result_data = ConfigUtilities::StudentResult($_POST['register_numbers'][$i], $subject_map_id_dum, $CIA, $INSERT_ESE_MARKS,$get_id_details_data['exam_year'],$get_id_details_data['exam_month']);

                                $model_save = new MarkEntry();
                                $model_save->student_map_id = $_POST['register_numbers'][$i];
                                $model_save->subject_map_id = $subject_map_id_dum;
                                $model_save->category_type_id =43;
                                $model_save->category_type_id_marks =0;
                                $model_save->year = $get_id_details_data['exam_year'];
                                $model_save->month = $get_id_details_data['exam_month'];
                                $model_save->term = $exam_term;
                                $model_save->mark_type = $exam_type;
                                $model_save->created_at = $created_at;
                                $model_save->created_by = $updateBy;
                                $model_save->updated_at = $created_at;
                                $model_save->updated_by = $updateBy;

                                if($model_save->save(false))
                                {
                                    $year_of_passing =  '';
                                    
                                    $markentrymaster = new MarkEntryMaster();
                                    $markentrymaster->student_map_id = $_POST['register_numbers'][$i];
                                    $markentrymaster->subject_map_id =$subject_map_id_dum;
                                    $markentrymaster->CIA = $CIA;
                                    $markentrymaster->ESE = 0;
                                    $markentrymaster->total = $CIA;
                                    $markentrymaster->result = 'Absent';
                                    $markentrymaster->grade_point = 0;
                                    $markentrymaster->grade_name = 'U';
                                    $markentrymaster->attempt = $stu_result_data['attempt'];
                                    $markentrymaster->year = $get_id_details_data['exam_year'];
                                    $markentrymaster->month = $get_id_details_data['exam_month'];
                                    $markentrymaster->term = $exam_term;
                                    $markentrymaster->mark_type = $exam_type;
                                    $markentrymaster->year_of_passing = $year_of_passing;
                                    $markentrymaster->status_id = 0;
                                    $markentrymaster->created_by = $updateBy;
                                    $markentrymaster->created_at = $created_at;
                                    $markentrymaster->updated_by = $updateBy;
                                    $markentrymaster->updated_at = $created_at;
                                 
                                    if($markentrymaster->save(false))
                                    {
                                        try
                            
                                        {
                                            Yii::$app->db->createCommand('UPDATE coe_valuation_mark_details SET approved_by="'.$updateBy.'", approved_at="'.$created_at.'" WHERE val_faculty_all_id="' . $val_facl_id[0] . '" AND stu_reg_no="'.$stu_reg['reg_num'].'"')->execute();

                                             $check_data = "SELECT * FROM coe_absent_entry WHERE absent_student_reg='".$_POST['register_numbers'][$i]."' AND exam_type='".$exam_type."' AND absent_term='".$exam_term."' and exam_month='".$get_id_details_data['exam_month']."' and exam_year='".$get_id_details_data['exam_year']."' AND exam_subject_id='".$subject_map_id_dum." '";
                                            
                                            $available_data = Yii::$app->db->createCommand($check_data)->queryAll();
                                            if(empty($available_data))
                                            {
                                                $query_insert = 'INSERT INTO coe_absent_entry (`absent_student_reg`,`exam_type`,`absent_term`,`exam_subject_id`,`exam_absent_status`,`exam_month`,`exam_year`,`created_by`,`created_at`,`updated_by`,`updated_at`) VALUES ("'.$_POST['register_numbers'][$i].'","'.$exam_type.'","'.$exam_term.'","'.$subject_map_id_dum.'","'.$get_cat_entry_type['coe_category_type_id'].'","'.$get_id_details_data['exam_month'].'","'.$get_id_details_data['exam_year'].'","'.$updateBy.'","'.$created_at.'","'.$updateBy.'","'.$created_at.'")';
                                                $Insert_absent = Yii::$app->db->createCommand($query_insert)->execute();
                                            }


                                            $totalSuccess+=1;
                                        }
                                        catch(\Exception $e)
                                        {

                                           $dispResults[] = ['type' => 'E',  'message' => ''];
                                        }
                                        
                                        $dispResults[] = ['type' => 'S',  'message' => 'Success']; 
                                    }
                                    else
                                    {
                                        $dispResults[] = ['type' => 'E',  'message' => 'Error'];
                                    }
                                }
                            }
                        }
                        else
                        {
                            Yii::$app->ShowFlashMessages->setMsg('Error','No Data Found!! Kindly Check CIA Mark.');
                            return $this->redirect(['dummy-numbers/dummy-number-entrynew']);  
                        }
                    }
                    else
                    {
                        $stu_reg=StuInfo::findOne(['stu_map_id'=>$_POST['register_numbers'][$i]]);
                        //Yii::$app->db->createCommand('UPDATE coe_valuation_mark_details SET approved_by="'.$updateBy.'", approved_at="'.$created_at.'" WHERE val_faculty_all_id="' . $val_facl_id[0] . '" AND stu_reg_no="'.$stu_reg['reg_num'].'"')->execute();
                    }
                }

                if(isset($totalSuccess) && $totalSuccess>0)
                {
                    Yii::$app->db->createCommand('UPDATE coe_valuation_faculty_allocate SET valuation_status="5" WHERE subject_code ="'.$qp_code.'"')->execute();
                
                    $get_allocate_id=Yii::$app->db->createCommand("SELECT val_faculty_all_id FROM coe_valuation_faculty_allocate G WHERE G.exam_month='" . $exam_month . "' AND G.exam_year='" . $exam_year . "' AND G.subject_code='".$qp_code."' and valuation_status=5")->queryAll();
   
                           
                    if(count($get_allocate_id)>0)
                    { 
     
                         Yii::$app->ShowFlashMessages->setMsg('Success','Data Inserted Successfully');
                        return $this->redirect(['dummy-numbers/dummy-number-entrynew']);
                    }  
                    else
                    {
                         Yii::$app->ShowFlashMessages->setMsg('Error','No Data Found! Please Check');
                        return $this->redirect(['dummy-numbers/dummy-number-entrynew']);
                    }
                          
                }
                else
                {
                    Yii::$app->ShowFlashMessages->setMsg('Error','Unable to Insert the Data. Please Check');
                     return $this->redirect(['dummy-numbers/dummy-number-entrynew']);
                }

            }
            else
            {
                Yii::$app->ShowFlashMessages->setMsg('Error','Something Error! Please Check');
                return $this->redirect(['dummy-numbers/dummy-number-entrynew']);
            }
                
           
        }
        else
        {
            Yii::$app->ShowFlashMessages->setMsg('Welcome','Welcome to '.ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_DUMMY).' Mark Entry');
           return $this->render('dummy-number-entrynew', [
                'model' => $model,
                'examtimetable' => $examtimetable,
                'factallModel' =>$factallModel,
                'verify_stu_data'=>$verify_stu_data,
                'mark_diff_count'=>'0',
                'valuation_status'=>'',
                'title'=>'',
                'exam_year'=>'',
            ]); 
        } 
        
    }