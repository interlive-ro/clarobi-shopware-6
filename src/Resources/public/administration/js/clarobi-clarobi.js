(this.webpackJsonp=this.webpackJsonp||[]).push([["clarobi-clarobi"],{"+Njd":function(e,t){const i=Shopware.Classes.ApiService,{Application:s}=Shopware;class n extends i{constructor(e,t,i="clarobi-api-test"){super(e,t,i)}check(e){const t=this.getBasicHeaders({});return this.httpClient.post(`_action/${this.getApiBasePath()}/verify`,e,{headers:t}).then(e=>i.handleResponse(e))}}s.addServiceProvider("clarobiApiTest",e=>{const t=s.getContainer("init");return new n(t.httpClient,e.loginService)})},"N+o0":function(e,t,i){"use strict";i.r(t);i("+Njd");var s=i("kezW"),n=i.n(s);const{Component:o,Mixin:a}=Shopware;o.register("clarobi-api-test-button",{template:n.a,props:["label"],inject:["clarobiApiTest"],mixins:[a.getByName("notification")],data:()=>({isLoading:!1,isSaveSuccessful:!1}),computed:{pluginConfig(){return this.$parent.$parent.$parent.actualConfigData.null}},methods:{saveFinish(){this.isSaveSuccessful=!1},check(){this.isLoading=!0,this.clarobiApiTest.check(this.pluginConfig).then(e=>{e.success?(this.isSaveSuccessful=!0,this.createNotificationSuccess({title:this.$tc("clarobi-api-test-button.title"),message:this.$tc("clarobi-api-test-button.success")})):this.createNotificationError({title:this.$tc("clarobi-api-test-button.title"),message:this.$tc("clarobi-api-test-button.error")}),this.isLoading=!1})}}});var c=i("uVU4"),r=i("yVnl");Shopware.Locale.extend("de-DE",c),Shopware.Locale.extend("en-GB",r)},kezW:function(e,t){e.exports='<div>\n    <sw-button-process\n        :isLoading="isLoading"\n        :processSuccess="isSaveSuccessful"\n        @process-finish="saveFinish"\n        @click="check"\n    >{{ label }}</sw-button-process>\n</div>\n'},uVU4:function(e){e.exports=JSON.parse('{"clarobi-api-test-button":{"title":"API Test","success":"Verbindung wurde erfolgreich getestet","error":"Verbindung konnte nicht hergestellt werden. Bitte stellen Sie sicher, dass Sie einen Clarobi für die aktuelle Domain haben!"}}')},yVnl:function(e){e.exports=JSON.parse('{"clarobi-api-test-button":{"title":"API Test","success":"Connection was successfully tested","error":"Connection could not be established. Please make sure you have a Clarobi for the current domain!"}}')}},[["N+o0","runtime"]]]);