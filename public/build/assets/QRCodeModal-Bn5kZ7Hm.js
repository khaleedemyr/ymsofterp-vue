import{Q as w}from"./browser-CCaIc0XY.js";import{r as _,a as v,L as g,B as u,D as t,N as l,G as h}from"./vendor-vue-g7iCcTkf.js";import"./vendor-utils-CpgP2uT4.js";const k={class:"fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4"},C={class:"bg-white rounded-2xl shadow-2xl max-w-md w-full"},Q={class:"bg-gradient-to-r from-blue-600 to-purple-600 text-white p-6 rounded-t-2xl"},R={class:"flex items-center justify-between"},D={class:"text-white/80 mt-2"},$={class:"p-6"},S={class:"text-center"},q={class:"bg-white p-4 rounded-lg inline-block border-2 border-gray-200"},T=["src"],j={key:1,class:"w-64 h-64 bg-gray-200 rounded flex items-center justify-center"},A={class:"mt-4 space-y-2"},F={class:"text-lg font-semibold text-gray-800"},B={class:"text-gray-600"},E={class:"text-gray-600"},N={class:"text-gray-600"},U={__name:"QRCodeModal",props:{training:Object},emits:["close"],setup(a,{emit:I}){const n=a,o=_(""),m=i=>new Date(i).toLocaleDateString("id-ID",{weekday:"long",year:"numeric",month:"long",day:"numeric"}),x=async()=>{try{if(n.training){const i=new Date(n.training.scheduled_date).toISOString().split("T")[0],e=n.training.id+n.training.course_id+i,s=await crypto.subtle.digest("SHA-256",new TextEncoder().encode(e)),d=Array.from(new Uint8Array(s)).map(y=>y.toString(16).padStart(2,"0")).join(""),c={schedule_id:n.training.id,course_id:n.training.course_id,scheduled_date:i,hash:d},f=JSON.stringify(c);o.value=await w.toDataURL(f,{width:300,margin:2,color:{dark:"#000000",light:"#FFFFFF"}}),console.log("QR Code generated successfully:",o.value),console.log("QR Code data:",c)}}catch(i){console.error("Error generating QR code:",i)}};v(()=>{x()});const p=()=>{var i;if(o.value){const e=document.createElement("a");e.href=o.value,e.download=`qr-code-${((i=n.training.course)==null?void 0:i.title)||"training"}-${n.training.scheduled_date}.png`,document.body.appendChild(e),e.click(),document.body.removeChild(e)}},b=()=>{var e,s,r;const i=window.open("","_blank");i.document.write(`
    <html>
      <head>
        <title>QR Code Training - ${(e=n.training.course)==null?void 0:e.title}</title>
        <style>
          body { font-family: Arial, sans-serif; text-align: center; padding: 20px; }
          .qr-container { margin: 20px 0; }
          .info { margin: 10px 0; }
          @media print { body { margin: 0; } }
        </style>
      </head>
      <body>
        <h1>QR Code Training</h1>
        <div class="qr-container">
          <img src="${n.training.qr_code_url}" alt="QR Code" style="width: 300px; height: 300px;" />
        </div>
        <div class="info">
          <h2>${(s=n.training.course)==null?void 0:s.title}</h2>
          <p>${m(n.training.scheduled_date)}</p>
          <p>${n.training.start_time} - ${n.training.end_time}</p>
          <p>${(r=n.training.outlet)==null?void 0:r.nama_outlet}</p>
        </div>
      </body>
    </html>
  `),i.document.close(),i.print()};return(i,e)=>{var s,r,d;return u(),g("div",k,[t("div",C,[t("div",Q,[t("div",R,[e[2]||(e[2]=t("h2",{class:"text-xl font-bold"},"QR Code Training",-1)),t("button",{onClick:e[0]||(e[0]=c=>i.$emit("close")),class:"text-white/80 hover:text-white"},e[1]||(e[1]=[t("i",{class:"fas fa-times text-xl"},null,-1)]))]),t("p",D,l(((s=a.training.course)==null?void 0:s.title)||"Training"),1)]),t("div",$,[t("div",S,[t("div",q,[o.value?(u(),g("img",{key:0,src:o.value,alt:"QR Code",class:"w-64 h-64"},null,8,T)):(u(),g("div",j,e[3]||(e[3]=[t("div",{class:"text-center"},[t("i",{class:"fas fa-qrcode text-gray-400 text-6xl mb-4"}),t("p",{class:"text-gray-500"},"Generating QR Code...")],-1)])))]),t("div",A,[t("h3",F,l((r=a.training.course)==null?void 0:r.title),1),t("p",B,l(m(a.training.scheduled_date)),1),t("p",E,l(a.training.start_time)+" - "+l(a.training.end_time),1),t("p",N,l((d=a.training.outlet)==null?void 0:d.nama_outlet),1)]),e[6]||(e[6]=t("div",{class:"mt-6 p-4 bg-blue-50 rounded-lg"},[t("h4",{class:"font-semibold text-blue-800 mb-2"},"Cara Penggunaan:"),t("ul",{class:"text-sm text-blue-700 space-y-1 text-left"},[t("li",null,"• QR Code ini unique untuk training ini"),t("li",null,"• Peserta dapat scan untuk check-in"),t("li",null,"• QR Code dapat digunakan untuk absensi"),t("li",null,"• Setiap jadwal training memiliki QR Code berbeda")])],-1)),t("div",{class:"mt-6 flex space-x-3"},[t("button",{onClick:p,class:"flex-1 px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition-colors"},e[4]||(e[4]=[t("i",{class:"fas fa-download mr-2"},null,-1),h(" Download QR ")])),t("button",{onClick:b,class:"flex-1 px-4 py-2 bg-green-500 text-white rounded-lg hover:bg-green-600 transition-colors"},e[5]||(e[5]=[t("i",{class:"fas fa-print mr-2"},null,-1),h(" Print QR ")]))])])])])])}}};export{U as default};
