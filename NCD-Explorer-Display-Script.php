<?php
    /* Render UI for NCD Devices and Functions */

    use Joomla\CMS\HTML\HTMLHelper;

    HTMLHelper::_('script', 'media/com_thinkiq/js/dist/tiq.core.min.js', array('version' => 'auto', 'relative' => false));
    HTMLHelper::_('script', 'media/com_thinkiq/js/dist/tiq.tiqGraphQL.min.js', array('version' => 'auto', 'relative' => false));
    HTMLHelper::_('script', 'media/com_thinkiq/js/dist/tiq.components.min.js', array('version' => 'auto', 'relative' => false));
    // HTMLHelper::_('script', 'media/com_thinkiq/js/dist/tiq.charts.min.js', array('version' => 'auto', 'relative' => false));

    require_once 'thinkiq_context.php';
    $context = new Context();

    //Find scripts
    use TiqUtilities\Model\Script;

    // ncd detector knows how to find new stuff
    $ncdDetector = new Script('ncd_sensors.ncd_object_discovery');
    $ncdDetector->script="";

    // ncd purge does what it's named for ...
    $ncdDeletor = new Script('ncd_sensors.ncd_purge');
    $ncdDeletor->script="";

    // let's get rid of this since we're not using it at all
    use Joomla\CMS\Factory;
    $user = Factory::getUser();

?>

<div id="app">

    <!-- Page Header -->    
    <div class="row">
        <div class="col-12">
        <h1 class="pb-2 pt-2" style="font-size:2.5rem; color:#126181;">
            {{pageTitle}}                
            <a v-if="true" class="float-end btn btn-sm btn-link mt-2" style="font-size:1rem; color:#126181;" v-bind:href="`/index.php?option=com_modeleditor&view=script&id=${context.std_inputs.script_id}`" target="_blank">
                source
            </a>            
        </h1>            
        <hr style="border-color:#126181; border-width:medium;" />        
        </div>       
    </div> 

    <!-- Toolbar -->
    <div class="mb-4">
        <button class="btn btn-primary" @click="DetectNCDDevices" style="background-color:darkgreen">Discover NCD Devices</button>
        <button class="btn btn-primary" onclick="location.reload();">Refresh</button>
        <button class="btn btn-primary" @click="PurgeNCDDevices" style="background-color:maroon">Remove NCD Devices</button>
        <span>Result: {{output}}</span>
    </div>

    <!-- Iterate through Gateways -->    
    <div v-for="aGateway in gateways" class="card w-100">
        <div class="card-body">
            <h5 class="card-title">
                {{aGateway.displayName}}
            </h5>            
            <h6 class="card-subtitle mb-2 text-muted">mac: {{aGateway.attributes[0].stringValue}}</h6>
            <div class="row mt-4">   
                         
                <!-- Iterate through Sensors -->                
                <div v-for="aSensor in aGateway.childEquipment" class="col-2">                    
                    <div class="card" style="min-height: 20rem;">                        
                        <div class="card-body">                            
                            <h5 class="card-title">{{aSensor.displayName}}</h5>                            
                            <h6 class="card-subtitle mb-2 text-muted">mac: {{aSensor.attributes.find(x=>x.displayName=="MAC").stringValue}}</h6>
                            <!-- More stuff for each sensor here -->
                            <!-- Maybe use v-if for different sensor types --> 
                            <div v-if="aSensor.typeName=='ncd_environment_sensor'"> 
                                <p>This is an environment sensor.</p>                           
                                <p>                         
                                    <label>Temperature:</label>         
                                    <span v-if="aSensor.attributes.find(x=>x.displayName=='Temperature').getTimeSeries.length>0">
                                        {{aSensor.attributes.find(x=>x.displayName=="Temperature").getTimeSeries[0].stringvalue}} °C   
                                    </span>  
                                    <sparkline-chart
                                        :id='aSensor.attributes.find(x=>x.displayName=="Temperature").id'
                                        :show-x-axis='false'
                                        :show-y-axis='false'
                                        :show-border='false'
                                        :show-tooltip='true'
                                        :duration='3600'
                                        :offset='0'
                                        :live-mode='true'
                                        :refresh-interval='10'
                                    />

                                </p>                  
                                <p>     
                                    <label>Humidity:</label>        
                                    <span v-if="aSensor.attributes.find(x=>x.displayName=='Humidity').getTimeSeries.length>0">
                                        {{aSensor.attributes.find(x=>x.displayName=="Humidity").getTimeSeries[0].stringvalue}} %     
                                    </span>
                                    <sparkline-chart
                                        :id='aSensor.attributes.find(x=>x.displayName=="Humidity").id'
                                        :show-x-axis='false'
                                        :show-y-axis='false'
                                        :show-border='false'
                                        :show-tooltip='true'
                                        :duration='3600'
                                        :offset='0'
                                        :live-mode='true'
                                        :refresh-interval='10'
                                    />
                                </p>                          
                            </div>            
                            <div v-if="aSensor.typeName=='ncd_light_sensor'">          
                                <p>This is a light sensor.</p>                            
                                <p>                            
                                    <label>Brightness:</label>     
                                    <span v-if="aSensor.attributes.find(x=>x.displayName=='Brightness').getTimeSeries.length>0">
                                        {{aSensor.attributes.find(x=>x.displayName=="Brightness").getTimeSeries[0].stringvalue}} lx     
                                    </span>
                                    <sparkline-chart
                                        :id='aSensor.attributes.find(x=>x.displayName=="Brightness").id'
                                        :show-x-axis='false'
                                        :show-y-axis='false'
                                        :show-border='false'
                                        :show-tooltip='true'
                                        :duration='3600'
                                        :offset='0'
                                        :live-mode='true'
                                        :refresh-interval='10'
                                    />
                                </p>                     
                                <p>                  
                                    <label>Proximity:</label> 
                                    <span v-if="aSensor.attributes.find(x=>x.displayName=='Proximity').getTimeSeries.length>0">
                                        {{aSensor.attributes.find(x=>x.displayName=="Proximity").getTimeSeries[0].stringvalue}} mm    
                                    </span>
                                    <sparkline-chart
                                        :id='aSensor.attributes.find(x=>x.displayName=="Proximity").id'
                                        :show-x-axis='false'
                                        :show-y-axis='false'
                                        :show-border='false'
                                        :show-tooltip='true'
                                        :duration='3600'
                                        :offset='0'
                                        :live-mode='true'
                                        :refresh-interval='10'
                                    />
                                </p>                    
                            </div>      
                        </div> 
                    </div>  
                </div>   
            </div>        
        </div>    
    </div>
</div>
<script>    
    var WinDoc = window.document;

    var app = new Vue({   

        el: "#app",  

        data() {
            return {                
                pageTitle: "NCD Explorer",   
                context:<?php echo json_encode($context) ?>,      
                user:<?php echo json_encode($user) ?>,   
                gateways: [],
                output: '',
                ncdDetectorApi : <?php echo json_encode($ncdDetector); ?>,
                ncdDeletorApi : <?php echo json_encode($ncdDeletor); ?>,
     
            }     
        },    

        mounted: async function () { 
            WinDoc.title = this.pageTitle;
            await this.GetGatewaysAsync();
        },
        
        computed: {
            NowIsh: function(){
                return new moment();
            }
        },

        methods: {

            CallScriptAsync: async function(scriptFileName, f, a){
                let apiRoute = `/index.php?option=com_thinkiq&task=invokeScript`;
                let settings = { method: 'POST', headers: {} };
                let formData = new FormData();
                formData.append('script_name', scriptFileName);
                formData.append('output_type', 'browser');
                formData.append('function', f);
                formData.append('argument', JSON.stringify(a));
                settings.body = formData;

                let aResponse = await fetch(apiRoute, settings);
                let aResponseData = await aResponse.json();
                return aResponseData;
            },

            DetectNCDDevices: async function(){
                console.log("calling: " + this.ncdDetectorApi.script_file_name);
                let aResponse = await this.CallScriptAsync(this.ncdDetectorApi.script_file_name, null, null)
                console.log(aResponse);
                this.output = "Discovery Complete!";

                // let's refresh the app, so we know who's new in town...
                await this.GetGatewaysAsync();

            },

            PurgeNCDDevices: async function(){
                console.log("calling: " + this.ncdDetectorApi.script_file_name);

                let aResponse = await this.CallScriptAsync(this.ncdDeletorApi.script_file_name, null, null)
                console.log(aResponse);
                this.output = "Purged all devices!";

                // let's refresh the app, so we know who's still standing...
                await this.GetGatewaysAsync();
            },

            GetGatewaysAsync: async function () {    
                let query = `            
                query MyQuery {           
                    object(id: "${this.context.std_inputs.node_id}") {    
                        id                  
                        displayName      
                        asThing {
                            equipmentByPartOfId {                
                                id                          
                                displayName                         
                                attributes {                
                                    id      
                                    displayName           
                                    stringValue                   
                                }                         
                                childEquipment {                     
                                    id                             
                                    displayName                     
                                    attributes {          
                                        id                                     
                                        displayName            
                                        relativeName       
                                        stringValue
                                        getTimeSeries(startTime:"${(new moment()).toISOString()}" endTime:"${(new moment()).toISOString()}") {
                                            id
                                            ts
                                            stringvalue
                                        }    
                                    }
                                    typeName                          
                                }            
                            }      
                        }            
                    }       
                }    
                `;
                let aResponse = await tiqGraphQL.makeRequestAsync(query);
                this.gateways = aResponse.data.object.asThing.equipmentByPartOfId;            
            }        
        },    
    });
</script>