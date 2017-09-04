<?php
require_once('accion.php');
   
class AccionSoap extends Accion {

    public function displaySecurityForm($proceso_id) {
        $data = Doctrine::getTable('Proceso')->find($proceso_id);
        $conf_seguridad = $data->Admseguridad;
        $display = '<p>
            Esta accion consultara via SOAP la siguiente URL. Los resultados, seran almacenados como variables.
            </p>';
        $display.='
                <div class="col-md-12">
                    <label>WSDL</label>
                    <input type="text" class="input-xxlarge AlignText" id="urlsoap" name="extra[wsdl]" value="' . ($this->extra ? $this->extra->wsdl : '') . '" />
                    <a class="btn btn-default" id="btn-consultar" ><i class="icon-search icon"></i> Consultar</a>
                    <a class="btn btn-default" href="#modalImportarWsdl" data-toggle="modal" ><i class="icon-upload icon"></i> Importar</a>
                </div>';
        $display.= '<label>Timeout</label>';
        $display.='<input type="text" placeholder="Tiempo en segundos..." name="extra[timeout]" value="' . ($this->extra ? $this->extra->timeout : '') . '" />';
        $display.='
                <div id="divMetodos" class="col-md-12">
                    <label>Métodos</label>
                    <select id="operacion" name="extra[operacion]">';
        if ($this->extra->operacion){
            $display.='<option value="'.($this->extra->operacion).'" selected>'.($this->extra->operacion).'</option>';
        }
        $display.='</select>
                </div>                
                <div id="divMetodosE" style="display:none;" class="col-md-12">
                    <span id="warningSpan" class="spanError"></span>
                    <br /><br />
                </div>';
        $display.='            
            <div class="col-md-12">
                <label>Request</label>
                <textarea id="request" name="extra[request]" rows="7" cols="70" placeholder="<xml></xml>" class="input-xxlarge">' . ($this->extra ? $this->extra->request : '') . '</textarea>
                <br />
                <!-- <span id="resultRequest" class="spanError"></span> -->
                <br /><br />
            </div>
           <div class="col-md-12">
                <label>Response</label>
                <textarea id="response" name="extra[response]" rows="7" cols="70" placeholder="{ object }" class="input-xxlarge" readonly>' . ($this->extra ? $this->extra->response : '') . '</textarea>
                <br /><br />
            </div>';
        $display.='<div id="modalImportarWsdl" class="modal hide fade">
                <form method="POST" enctype="multipart/form-data" action="backend/acciones/upload_file">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
                    <h3>Importar Archivo Soap</h3>
                </div>
                <div class="modal-body">
                    <p>Cargue a continuación el archivo .wsdl del Servio Soap.</p>
                    <input type="file" name="archivo" />
                </div>
                <div class="modal-footer">
                    <button class="btn" data-dismiss="modal" aria-hidden="true">Cerrar</button>
                    <button type="button" id="btn-load" class="btn btn-primary">Importar</button>
                </div>
                </form>
            </div>
            <div id="modal" class="modal hide fade"></div>';
        $display.='<label>Seguridad</label>
                <select id="tipoSeguridad" name="extra[idSeguridad]">';
        foreach($conf_seguridad as $seg){
            $display.='<option value="">Sin seguridad</option>';
            if ($this->extra->idSeguridad && $this->extra->idSeguridad == $seg->id){
                $display.='<option value="'.$seg->id.'" selected>'.$seg->institucion.' - '.$seg->servicio.'</option>';
            }else{
                $display.='<option value="'.$seg->id.'">'.$seg->institucion.' - '.$seg->servicio.'</option>';
            }
        }
        $display.='</select>';
        return $display;
    }

    public function validateForm() {
        $CI = & get_instance();
        $CI->form_validation->set_rules('extra[request]', 'Request', 'required');
        $CI->form_validation->set_rules('extra[operacion]', 'Métodos', 'required');
    }

    public function ejecutar(Etapa $etapa) { 
        $data = Doctrine::getTable('Seguridad')->find($this->extra->idSeguridad);
        $tipoSeguridad=$data->extra->tipoSeguridad;
        $user = $data->extra->user;
        $pass = $data->extra->pass;
        $ApiKey = $data->extra->apikey;
        
        //Se declara el cliente soap
        $client = new nusoap_client($this->extra->wsdl, 'wsdl');
        
        // Se asigna valor de timeout
        $client->soap_defencoding = 'UTF-8';
        $client->decode_utf8 = true;
        $client->timeout = $this->extra->timeout;
        $client->response_timeout = $this->extra->timeout;
        
        //Se instancia el tipo de seguridad segun sea el caso
        switch ($tipoSeguridad) {
            case "HTTP_BASIC":
                //SEGURIDAD BASIC
                $client->setCredentials($user, $pass, 'basic');
                break;
            case "API_KEY":
                //SEGURIDAD API KEY
                $header = 
                "<SECINFO>
                  <KEY>".$this->extra->apikey."</KEY>
                </SECINFO>";
                $client->setHeaders($header);
                break;
            case "OAUTH2":
                //SEGURIDAD OAUTH2
                $client->setCredentials($user, $pass, 'basic');
                break;
            default:
                //NO TIENE SEGURIDAD
            break;
        }
        try{
            $CI = & get_instance();
            $r=new Regla($this->extra->wsdl);
            $wsdl=$r->getExpresionParaOutput($etapa->id);
            if(isset($this->extra->request)){
                $r=new Regla($this->extra->request);
                $request=$r->getExpresionParaOutput($etapa->id);
            }
            
            //Se EJECUTA el llamado Soap
            $result_soap = $client->call($this->extra->operacion, $request,null,'',false,null,'rpc','literal', true);
            log_message('info', 'Result: '.$this->varDump($result_soap), FALSE);
            log_message('info', 'Client data: '.$this->varDump($client->document), FALSE);
            $error = $client->getError();

            if ($error){
                $result['response_soap']= $error;   
            }else{
                $result['response_soap']= $this->utf8ize($result_soap);//$client->document;//$client->response;
            }

            log_message('info', 'Result a variable: '.$this->varDump($result), FALSE);
            foreach($result as $key=>$value){

                log_message('info', 'key '.$key.': '.$this->varDump($value), FALSE);

                /*$xml=simplexml_load_string($value);
                if($xml){
                    log_message('info', 'ES UN XML ::::::::::::::::::::::::::::: '.$this->varDump(" ::::::::::::: ES XML "), FALSE);                    
                    $valor = get_object_vars($xml);
                }else{
                    log_message('info', 'NO ES XML ::::::::::::::::::::::::::::: '.$this->varDump(" :::::::::::::::: NO ES XML"), FALSE); 
                    $valor = json_encode($value);
                }*/
                $dato=Doctrine::getTable('DatoSeguimiento')->findOneByNombreAndEtapaId($key,$etapa->id);
                if(!$dato){
                    log_message('info', 'Dato no existe, se crea nuevo', FALSE);
                    $dato=new DatoSeguimiento();
                }
                $dato->nombre=$key;
                $dato->valor=$value;
                $dato->etapa_id=$etapa->id;
                log_message('info', 'Nombre dato a guardar: '.$this->varDump($dato->nombre), FALSE);
                log_message('info', 'Valor dato a guardar: '.$this->varDump($dato->valor), FALSE);
                log_message('info', 'etapa_id dato a guardar: '.$this->varDump($dato->etapa_id), FALSE);
                $dato->save();
            }
        }catch (Exception $e){
            log_message('info', 'Exception: '.$this->varDump($e), FALSE);
            $dato=Doctrine::getTable('DatoSeguimiento')->findOneByNombreAndEtapaId("error_soap",$etapa->id);
            if(!$dato)
                $dato=new DatoSeguimiento();
            $dato->nombre="error_soap";
            $dato->valor=$e;
            $dato->etapa_id=$etapa->id;
            $dato->save();
        }
    }
    function varDump($data){
        ob_start();
        //var_dump($data);
        print_r($data);
        $ret_val = ob_get_contents();
        ob_end_clean();
        return $ret_val;
    }

    private function utf8ize($d) {
        try{
            if (is_array($d))
                foreach ($d as $k => $v)
                    $d[$k] = $this->utf8ize($v);

            else if(is_object($d))
                foreach ($d as $k => $v)
                    $d->$k = $this->utf8ize($v);

            else
                return utf8_encode($d);
        }catch (Exception $e){
            log_message('info', 'Exception utf8ize: '.$this->varDump($e), FALSE);
        }

        return $d;
    }
}