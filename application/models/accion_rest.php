<?php
require_once('accion.php');

class AccionRest extends Accion {

    public function displaySecurityForm($proceso_id) {
        $data = Doctrine::getTable('Proceso')->find($proceso_id);
        $conf_seguridad = $data->Admseguridad;
        $display = '
            <p>
                Esta accion consultara via REST la siguiente URL. Los resultados, seran almacenados en la variable de respuesta definida.
            </p>
        ';
        $display.= '<label>Variable respuesta</label>';
        $display.='<input type="text" name="extra[var_response]" value="' . ($this->extra ? $this->extra->var_response : '') . '" />';
        $display.= '<label>Endpoint</label>';
        $display.='<input type="text" class="input-xxlarge" placeholder="Server" name="extra[url]" value="' . ($this->extra ? $this->extra->url : '') . '" />';
        $display.= '<label>Resource</label>';
        $display.='<input type="text" class="input-xxlarge" placeholder="Uri" name="extra[uri]" value="' . ($this->extra ? $this->extra->uri : '') . '" />';
        $display.='
                <label>Método</label>
                <select id="tipoMetodo" name="extra[tipoMetodo]">
                    <option value="">Seleccione...</option>';
                    if ($this->extra->tipoMetodo && $this->extra->tipoMetodo == "POST"){
                        $display.='<option value="POST" selected>POST</option>';
                    }else{
                        $display.='<option value="POST">POST</option>';
                    }
                    if ($this->extra->tipoMetodo && $this->extra->tipoMetodo == "GET"){
                        $display.='<option value="GET" selected>GET</option>';
                    }else{
                        $display.='<option value="GET">GET</option>';
                    }
                    if ($this->extra->tipoMetodo && $this->extra->tipoMetodo == "PUT"){
                        $display.='<option value="PUT" selected>PUT</option>';
                    }else{
                        $display.='<option value="PUT">PUT</option>';
                    }
                    if ($this->extra->tipoMetodo && $this->extra->tipoMetodo == "DELETE"){
                        $display.='<option value="DELETE" selected>DELETE</option>';
                    }else{
                        $display.='<option value="DELETE">DELETE</option>';
                    }
        $display.='</select>';
        $display.= '<label>Timeout</label>';
        $display.='<input type="text" placeholder="Tiempo en segundos..." name="extra[timeout]" value="' . ($this->extra ? $this->extra->timeout : '') . '" />';

        $display.= '<label>N&uacute;mero reintentos</label>';
        $display.='<input type="text" name="extra[timeout_reintentos]" value="' . ($this->extra ? $this->extra->timeout_reintentos : '3') . '" />';

        if ($this->extra->tipoMetodo && ($this->extra->tipoMetodo == "PUT" || $this->extra->tipoMetodo == "POST")){
            $display.='
            <div class="col-md-12" id="divObject">
                <label>Request</label>
                <textarea id="request" name="extra[request]" rows="7" cols="70" placeholder="{ object }" class="input-xxlarge">' . ($this->extra ? $this->extra->request : '') . '</textarea>
                <br />
                <span id="resultRequest" class="spanError"></span>
                <br /><br />
            </div>';
        }else{
            $display.='
            <div class="col-md-12" id="divObject" style="display:none;">
                <label>Request</label>
                <textarea id="request" name="extra[request]" rows="7" cols="70" placeholder="{ object }" class="input-xxlarge">' . ($this->extra ? $this->extra->request : '') . '</textarea>
                <br />
                <span id="resultRequest" class="spanError"></span>
                <br /><br />
            </div>';
        }
        $display.='
            <div class="col-md-12">
                <label>Header</label>
                <textarea id="header" name="extra[header]" rows="7" cols="70" placeholder="{ Header }" class="input-xxlarge">' . ($this->extra ? $this->extra->header : '') . '</textarea>
                <br />
                <span id="resultHeader" class="spanError"></span>
                <br /><br />
            </div>';
        $display.='
                <label>Seguridad</label>
                <select id="tipoSeguridad" name="extra[idSeguridad]">';
                foreach($conf_seguridad as $seg){
                    $display.='
                        <option value="-1">Sin seguridad</option>';
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
        $CI->form_validation->set_rules('extra[url]', 'Endpoint', 'required');
        $CI->form_validation->set_rules('extra[uri]', 'Resource', 'required');
        $CI->form_validation->set_rules('extra[var_response]', 'Variable de respuesta', 'required');
    }

    public function ejecutar(Etapa $etapa) {

        try{

            log_message("INFO", "Ejecutando llamado REST", FALSE);

            $CI = & get_instance();
            ($this->extra->timeout ? $timeout = $this->extra->timeout : $timeout = 30);

            $r=new Regla($this->extra->url);
            $server=$r->getExpresionParaOutput($etapa->id);
            $caracter="/";
            $f = substr($server, -1);
            if($caracter===$f){
                $server = substr($server, 0, -1);
            }

            $r=new Regla($this->extra->uri);
            $uri=$r->getExpresionParaOutput($etapa->id);
            $l = substr($uri, 0, 1);
            if($caracter===$l){
                $uri = substr($uri, 1);
            }

            log_message("INFO", "Server: ".$server, FALSE);
            log_message("INFO", "Resource: ".$uri, FALSE);

            $seguridad = new SeguridadIntegracion();
            $config = $seguridad->getConfigRest($this->extra->idSeguridad, $server);

            if(isset($this->extra->request)){
                $r=new Regla($this->extra->request);
                $request=$r->getExpresionParaOutput($etapa->id);
            }

            log_message("INFO", "Request: ".$request, FALSE);

            //obtenemos el Headers si lo hay
            if(isset($this->extra->header)){
                $r=new Regla($this->extra->header);
                $header=$r->getExpresionParaOutput($etapa->id);
                $headers = json_decode($header);

                if( isset($header) && trim($header) != ''){

                    foreach ($headers as $name => $value) {
                        $CI->rest->header($name.": ".$value);
                    }
                }
            }

            $CI->rest->initialize($config);

            $intentos = 1;
            do{

                // Se ejecuta la llamada segun el metodo
                if($this->extra->tipoMetodo == "GET"){
                    $result = $CI->rest->get($uri, array() , 'json');
                }else if($this->extra->tipoMetodo == "POST"){
                    $result = $CI->rest->post($uri, $request, 'json');
                }else if($this->extra->tipoMetodo == "PUT"){
                    $result = $CI->rest->put($uri, $request, 'json');
                }else if($this->extra->tipoMetodo == "DELETE"){
                    $result = $CI->rest->delete($uri, $request, 'json');
                }
                $debug = $CI->rest->debug();
                //se verifica si existe numero de reintentos
                $reintentos = 0;
                if(isset($this->extra->timeout_reintentos)){
                    $reintentos = $this->extra->timeout_reintentos;
                }
                if(isset($debug['error_code']) && $debug['error_code'] == '28') {
                    log_message("INFO", "Reintentando " . $this->extra->timeout_reintentos . " veces.", FALSE);
                    $intentos++;
                }

            }while($intentos < $reintentos && $debug['error_code'] == '28');

            if($debug['info']['http_code']=='204'){
                $result2['code']= '204';
                $result2['des_code']= 'No Content';
            }else if($debug['info']['http_code']=='0'){
                $result2['code']= $debug['error_code'];
                $result2['des_code']= $debug['response_string'];
            }else{
               
                if(!is_array($result) && !is_object($result)) {
                    $result2['code']= '2';
                    $result2['des_code']= $debug['response_string'];
                }else{
                    
                    $result2 = (is_array($result)) ? get_object_vars($result[0]):get_object_vars($result);
                }
            }
            //$response["response".$this->extra->tipoMetodo]=$result2;
            $response[$this->extra->var_response]=$result2;


            foreach($response as $key=>$value){
                $dato=Doctrine::getTable('DatoSeguimiento')->findOneByNombreAndEtapaId($key,$etapa->id);
                if(!$dato)
                    $dato=new DatoSeguimiento();
                $dato->nombre=$key;
                $dato->valor=$value;
                $dato->etapa_id=$etapa->id;
                $dato->save();
            }
        }catch (Exception $e){
            log_message("INFO", "Error: ".$e->getMessage(), FALSE);
            $dato=Doctrine::getTable('DatoSeguimiento')->findOneByNombreAndEtapaId("error_rest",$etapa->id);
            if(!$dato)
                $dato=new DatoSeguimiento();
            $dato->nombre="error_rest";
            $dato->valor=$e->getMessage();
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
}