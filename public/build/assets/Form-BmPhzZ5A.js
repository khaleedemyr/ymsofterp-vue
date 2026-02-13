import{r as w,c as R,w as X,A as mt,B as o,C as ct,D as e,L as r,E as f,G as p,H as T,J as Y,I as Z,N as i,l as L,F as M,M as V,O as vt,V as xt}from"./vendor-vue-g7iCcTkf.js";import{A as pt}from"./AppLayout-BpSu8nlk.js";import{a as tt}from"./vendor-utils-CpgP2uT4.js";import{S}from"./app-C7BJ3pFi.js";import{_ as gt}from"./_plugin-vue_export-helper-DlAUqK2U.js";import"./ProfileUpdateModal-DDWCBsJM.js";import"./InputLabel-Bg0AMQZ3.js";import"./PrimaryButton-BbBAD9SX.js";import"./TextInput-B7P_xVe4.js";import"./vendor-charts-CuqD4wLC.js";const bt={class:"max-w-4xl mx-auto py-4 px-3"},ft={class:"mb-8"},yt={class:"bg-white p-3 rounded-lg border border-gray-200 mb-3"},ht={class:"space-y-3"},_t={class:"relative"},wt={key:0,class:"absolute right-2 top-2"},kt={key:0,class:"space-y-3"},Ot={class:"flex justify-between items-center"},Ct={class:"text-sm font-semibold text-gray-900"},Pt={class:"flex gap-1"},$t={key:0,class:"grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2"},qt=["onClick"],St={class:"flex justify-between items-start mb-1"},It={class:"font-medium text-gray-900 text-xs truncate flex-1 mr-1"},Rt={class:"text-xs font-mono text-blue-600 mb-1"},Tt={class:"text-xs text-gray-500 mb-1"},Lt={class:"flex items-center mb-0.5"},Dt={class:"truncate"},Nt={class:"flex items-center"},Bt={class:"truncate"},Ut={class:"text-xs text-gray-600 mb-1"},At={class:"truncate"},Mt={class:"text-xs text-gray-500"},Vt={key:1,class:"bg-white border border-gray-200 rounded-md overflow-hidden"},Ft={class:"overflow-x-auto"},Kt={class:"min-w-full divide-y divide-gray-200"},Qt={class:"bg-white divide-y divide-gray-200"},zt=["onClick"],Gt={class:"px-3 py-2 whitespace-nowrap text-xs font-medium text-gray-900"},Wt={class:"px-3 py-2 whitespace-nowrap text-xs font-mono text-blue-600"},jt={class:"px-3 py-2 whitespace-nowrap text-xs text-gray-500"},Et={class:"px-3 py-2 whitespace-nowrap text-xs text-gray-500"},Yt={class:"px-3 py-2 whitespace-nowrap"},Ht={class:"px-3 py-2 whitespace-nowrap text-xs text-gray-500"},Jt=["onClick"],Xt={key:1,class:"text-center py-8"},Zt={key:2,class:"bg-blue-50 border border-blue-200 rounded-md p-3"},te={class:"grid grid-cols-2 gap-3 text-xs"},ee={class:"text-xs text-blue-900"},se={class:"text-xs font-mono text-blue-900"},le={class:"text-xs text-blue-900"},ae={class:"text-xs text-blue-900"},ie={class:"text-xs text-blue-900"},oe={class:"text-xs text-blue-900"},ne={key:0,id:"warehouse-selection",class:"mb-6"},re={class:"bg-blue-50 border-l-4 border-blue-400 p-3 rounded mb-3"},de={key:0,class:"text-xs space-y-1"},ue={key:0},me={key:1},ce={class:"mb-3"},ve=["value"],xe={key:1},pe={key:0,class:"text-center text-gray-500 my-6"},ge={key:1,class:"text-center py-6"},be={key:2,class:"text-red-600 mb-3 text-sm"},fe={key:3},ye={class:"mb-3"},he={class:"text-sm text-gray-600"},_e={class:"font-bold text-blue-700 text-sm mb-2"},we={class:"overflow-x-auto"},ke={class:"w-full text-xs"},Oe={class:"py-1 px-2"},Ce={class:"py-1 px-2"},Pe=["onUpdate:modelValue"],$e={class:"py-1 px-2"},qe={class:"py-1 px-2"},Se={class:"py-1 px-2"},Ie=["onUpdate:modelValue"],Re={class:"py-1 px-2"},Te={class:"py-1 px-2"},Le=["onClick"],De={class:"flex gap-2"},Ne=["disabled"],Be=["disabled"],Ue={key:0,class:"ml-2"},Ae={__name:"Form",props:{floorOrders:Array,warehouseDivisions:Array},setup(et){const k=et,v=w(""),g=w(""),m=w([]),F=w(!1),K=w(""),D=w(!1),P=w(""),N=w(""),$=w(null),B=w("cards"),U=w(""),y=R(()=>k.floorOrders.find(s=>s.id==v.value)||{});R(()=>{const s=k.warehouseDivisions.find(t=>t.id==g.value);return s?s.name:"-"});const st=R(()=>{const s={};return m.value.forEach(t=>{var l,u;const n=((u=(l=t.item)==null?void 0:l.category)==null?void 0:u.name)||"Tanpa Kategori";s[n]||(s[n]=[]),s[n].push(t)}),s}),Q=R(()=>{let s=k.floorOrders||[];if(P.value){const t=P.value.toLowerCase();s=s.filter(n=>{var l,u,h,_,d;return((u=(l=n.outlet)==null?void 0:l.nama_outlet)==null?void 0:u.toLowerCase().includes(t))||((h=n.order_number)==null?void 0:h.toLowerCase().includes(t))||((d=(_=n.user)==null?void 0:_.nama_lengkap)==null?void 0:d.toLowerCase().includes(t))||b(n.tanggal).toLowerCase().includes(t)})}return N.value&&(s=s.filter(t=>t.status===N.value)),U.value&&(s=s.filter(t=>t.arrival_date?new Date(t.arrival_date).toISOString().split("T")[0]===U.value:!1)),s}),q=R(()=>{var s;return(s=k.floorOrders)==null?void 0:s.find(t=>t.id===$.value)}),b=s=>s?new Date(s).toLocaleDateString("id-ID",{day:"2-digit",month:"2-digit",year:"numeric"}):"-",H=s=>{switch(s){case"approved":return"bg-green-100 text-green-800";case"packing":return"bg-yellow-100 text-yellow-800";default:return"bg-gray-100 text-gray-800"}},j=s=>{$.value=s,v.value=s},lt=()=>{$.value=null,v.value=""},at=()=>{P.value=""},it=()=>{},ot=()=>{P.value="",N.value="",U.value=""},nt=s=>{s.input_qty=s.qty??s.qty_order},I=R(()=>{const s=m.value.filter(l=>l.checked),t=s.length,n=s.reduce((l,u)=>l+(u.input_qty||0),0);return{selectedItems:s,totalItems:t,totalQty:n,warehouseDivision:k.warehouseDivisions.find(l=>l.id==g.value),floorOrder:k.floorOrders.find(l=>l.id==v.value)}}),rt=async()=>{var u,h,_,d,O;if(!v.value||!g.value||m.value.length===0)return;const s=m.value.filter(x=>x.checked);if(s.length===0){await S.fire({icon:"warning",title:"Peringatan",text:"Pilih minimal satu item untuk di-packing!"});return}if(s.filter(x=>!x.input_qty||x.input_qty<=0).length>0){await S.fire({icon:"warning",title:"Peringatan",text:"Semua item yang dipilih harus memiliki quantity yang valid!"});return}const n=`
    <div class="text-left">
      <div class="mb-4">
        <h3 class="font-bold text-lg mb-2 text-blue-600">Summary Packing List</h3>
        
        <!-- RO Info -->
        <div class="bg-blue-50 p-3 rounded-lg mb-3">
          <h4 class="font-semibold text-blue-800 mb-2">Detail Request Order</h4>
          <div class="grid grid-cols-2 gap-2 text-sm">
            <div><span class="font-medium">Outlet:</span> ${((h=(u=I.value.floorOrder)==null?void 0:u.outlet)==null?void 0:h.nama_outlet)||"-"}</div>
            <div><span class="font-medium">Nomor RO:</span> <span class="font-mono">${((_=I.value.floorOrder)==null?void 0:_.order_number)||"-"}</span></div>
            <div><span class="font-medium">Tanggal:</span> ${b((d=I.value.floorOrder)==null?void 0:d.tanggal)}</div>
            <div><span class="font-medium">Warehouse Division:</span> ${((O=I.value.warehouseDivision)==null?void 0:O.name)||"-"}</div>
          </div>
        </div>

        <!-- Items Summary -->
        <div class="bg-gray-50 p-3 rounded-lg mb-3">
          <h4 class="font-semibold text-gray-800 mb-2">Items yang akan di-packing</h4>
          <div class="text-sm mb-2">
            <span class="font-medium">Total Items:</span> ${I.value.totalItems} item(s)
          </div>
          <div class="text-sm mb-3">
            <span class="font-medium">Total Quantity:</span> ${I.value.totalQty}
          </div>
          
          <div class="max-h-40 overflow-y-auto">
            <table class="w-full text-xs">
              <thead class="bg-gray-100">
                <tr>
                  <th class="py-1 px-2 text-left">No</th>
                  <th class="py-1 px-2 text-left">Item</th>
                  <th class="py-1 px-2 text-left">Qty Order</th>
                  <th class="py-1 px-2 text-left">Qty Packing</th>
                  <th class="py-1 px-2 text-left">Unit</th>
                </tr>
              </thead>
              <tbody>
                ${s.map((x,z)=>{var A;return`
                  <tr class="border-b border-gray-200">
                    <td class="py-1 px-2">${z+1}</td>
                    <td class="py-1 px-2">${((A=x.item)==null?void 0:A.name)||x.item_name}</td>
                    <td class="py-1 px-2 text-right">${x.qty??x.qty_order}</td>
                    <td class="py-1 px-2 text-right font-medium text-blue-600">${x.input_qty}</td>
                    <td class="py-1 px-2">${x.unit}</td>
                  </tr>
                `}).join("")}
              </tbody>
            </table>
          </div>
        </div>

        <div class="text-sm text-gray-600">
          <i class="fas fa-info-circle mr-1"></i>
          Pastikan semua data sudah benar sebelum melanjutkan.
        </div>
      </div>
    </div>
  `;(await S.fire({title:"Konfirmasi Packing List",html:n,icon:"question",showCancelButton:!0,confirmButtonText:"Ya, Buat Packing List",cancelButtonText:"Batal",confirmButtonColor:"#3B82F6",cancelButtonColor:"#6B7280",width:"600px",customClass:{container:"summary-modal-container"}})).isConfirmed&&await dt()};async function dt(){var t,n;if(!v.value||!g.value||m.value.length===0)return;const s={food_floor_order_id:v.value,warehouse_division_id:g.value,items:m.value.filter(l=>l.checked).map(l=>({food_floor_order_item_id:l.id,qty:l.input_qty??0,unit:l.unit,source:l.source,reason:l.reason||null}))};D.value=!0;try{const l=await tt.post("/packing-list",s);D.value=!1,await S.fire({icon:"success",title:"Berhasil",text:"Packing List berhasil dibuat!"}),window.location.href="/packing-list"}catch(l){D.value=!1,await S.fire({icon:"error",title:"Gagal",text:((n=(t=l==null?void 0:l.response)==null?void 0:t.data)==null?void 0:n.message)||"Gagal membuat Packing List."})}}X([v,g],async([s,t])=>{if(s&&t){F.value=!0,K.value="";try{const n=await tt.get("/api/packing-list/available-items",{params:{fo_id:s,division_id:t}});m.value=(n.data.items||[]).map(l=>({...l,source:"warehouse",checked:!0,input_qty:null}))}catch{K.value="Gagal mengambil data item.",m.value=[]}finally{F.value=!1}}else m.value=[]}),X($,s=>{s&&(v.value=s)});const ut=()=>{var h,_;if(!v.value||!g.value||m.value.length===0){S.fire({icon:"warning",title:"Peringatan",text:"Pilih RO dan Warehouse Division terlebih dahulu!"});return}const s=m.value.filter(d=>d.checked);if(s.length===0){S.fire({icon:"warning",title:"Peringatan",text:"Pilih minimal satu item untuk di-print!"});return}const t=k.floorOrders.find(d=>d.id==v.value),n=k.warehouseDivisions.find(d=>d.id==g.value),l={orderNumber:(t==null?void 0:t.order_number)||"-",date:b(t==null?void 0:t.tanggal),outlet:((h=t==null?void 0:t.outlet)==null?void 0:h.nama_outlet)||"-",items:s.map(d=>{var O;return{name:((O=d.item)==null?void 0:O.name)||d.item_name||"-",qty:d.input_qty||d.qty||d.qty_order||0,unit:d.unit||"-"}}),divisionName:(n==null?void 0:n.name)||"-",roNumber:(t==null?void 0:t.order_number)||"-",roDate:b(t==null?void 0:t.tanggal),roCreatorName:((_=t==null?void 0:t.user)==null?void 0:_.nama_lengkap)||"-",arrivalDate:t!=null&&t.arrival_date?b(t==null?void 0:t.arrival_date):"-"},u=window.open("","_blank","width=600,height=600");u.document.write(`
    <!DOCTYPE html>
    <html>
    <head>
      <title>Packing List - ${l.orderNumber}</title>
      <style>
        @media print {
          @page {
            size: 58mm auto;
            margin: 0;
          }
          body {
            width: 58mm;
            margin: 0;
            padding: 2mm;
            font-family: 'Courier New', monospace;
            font-size: 10px;
            line-height: 1.2;
          }
        }
        body {
          font-family: 'Courier New', monospace;
          font-size: 10px;
          line-height: 1.2;
          width: 58mm;
          margin: 0;
          padding: 2mm;
        }
        .header {
          text-align: center;
          font-weight: bold;
          margin-bottom: 4mm;
        }
        .title {
          font-size: 12px;
          margin-bottom: 2mm;
        }
        .company {
          font-size: 10px;
          margin-bottom: 2mm;
        }
        .info {
          margin-bottom: 4mm;
        }
        .info div {
          margin-bottom: 1mm;
        }
        .separator {
          border-top: 1px solid #000;
          margin: 2mm 0;
        }
        .items {
          margin-bottom: 4mm;
        }
        .item {
          margin-bottom: 2mm;
        }
                 .item-name {
           font-weight: bold;
         }
        .summary {
          margin-top: 4mm;
        }
        .footer {
          text-align: center;
          margin-top: 4mm;
          font-size: 9px;
        }
        @media screen {
          body {
            border: 1px solid #ccc;
            margin: 10px auto;
          }
        }
      </style>
    </head>
    <body>
      <div class="header">
        <div class="title">PACKING LIST</div>
        <div class="company">JUSTUS GROUP</div>
        <div class="company">${l.divisionName}</div>
      </div>
      
      <div class="info">
        <div>No: ${l.orderNumber}</div>
        <div>Tanggal: ${l.date}</div>
        <div>Outlet: ${l.outlet}</div>
        <div>RO: ${l.roNumber}</div>
        <div>Tgl RO: ${l.roDate}</div>
        <div>Kedatangan: ${l.arrivalDate}</div>
        <div>Pembuat RO: ${l.roCreatorName}</div>
      </div>
      
      <div class="separator"></div>
      
               <div class="items">
           <div style="font-weight: bold; margin-bottom: 2mm;">ITEMS:</div>
                       ${l.items.map((d,O)=>`
              <div class="item">
                <div class="item-name">${d.qty} ${d.unit} ${d.name}</div>
              </div>
            `).join("")}
         </div>
      
      <div class="separator"></div>
      
             <div class="summary">
         <div style="font-weight: bold; margin-bottom: 2mm;">SUMMARY:</div>
         <div>Total Items: ${l.items.length}</div>
       </div>
      
      <div class="footer">
        <div>Generated: ${new Date().toLocaleString("id-ID")}</div>
        <div style="margin-top: 2mm;">Terima kasih</div>
      </div>
    </body>
    </html>
  `),u.document.close(),u.focus(),setTimeout(()=>{u.print()},500)};return(s,t)=>(o(),mt(pt,null,{default:ct(()=>{var n,l,u,h,_,d,O,x,z,A,J;return[e("div",bt,[t[49]||(t[49]=e("h1",{class:"text-lg font-bold text-gray-800 mb-4 flex items-center gap-2"},[e("i",{class:"fa-solid fa-box text-blue-500"}),p(" Buat Packing List ")],-1)),e("div",ft,[t[28]||(t[28]=e("h2",{class:"text-lg font-semibold text-gray-700 mb-4"},"1. Pilih Request Order (RO)",-1)),e("div",yt,[e("div",ht,[e("div",null,[t[8]||(t[8]=e("label",{class:"block text-xs font-medium text-gray-700 mb-1"},"Cari RO",-1)),e("div",_t,[T(e("input",{type:"text","onUpdate:modelValue":t[0]||(t[0]=a=>P.value=a),placeholder:"Outlet, nomor RO, atau tanggal...",class:"w-full px-3 py-2 pl-8 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500"},null,512),[[Y,P.value]]),t[7]||(t[7]=e("div",{class:"absolute left-2 top-2"},[e("i",{class:"fas fa-search text-gray-400 text-xs"})],-1)),P.value?(o(),r("div",wt,[e("button",{onClick:at,class:"text-gray-400 hover:text-gray-600"},t[6]||(t[6]=[e("i",{class:"fas fa-times text-xs"},null,-1)]))])):f("",!0)])]),e("div",null,[t[10]||(t[10]=e("label",{class:"block text-xs font-medium text-gray-700 mb-1"},"Status",-1)),T(e("select",{"onUpdate:modelValue":t[1]||(t[1]=a=>N.value=a),class:"w-full px-3 py-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500"},t[9]||(t[9]=[e("option",{value:""},"Semua Status",-1),e("option",{value:"approved"},"Approved",-1),e("option",{value:"packing"},"Packing",-1)]),512),[[Z,N.value]])]),e("div",null,[t[11]||(t[11]=e("label",{class:"block text-xs font-medium text-gray-700 mb-1"},"Tanggal Kedatangan",-1)),T(e("input",{type:"date","onUpdate:modelValue":t[2]||(t[2]=a=>U.value=a),onChange:it,class:"w-full px-3 py-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500"},null,544),[[Y,U.value]])]),e("div",null,[e("button",{onClick:ot,class:"w-full px-3 py-2 text-sm bg-gray-100 text-gray-700 rounded-md hover:bg-gray-200 transition-colors"},t[12]||(t[12]=[e("i",{class:"fas fa-times mr-2"},null,-1),p("Clear All Filters ")]))])])]),Q.value.length>0?(o(),r("div",kt,[e("div",Ot,[e("h3",Ct," RO Tersedia ("+i(Q.value.length)+") ",1),e("div",Pt,[e("button",{onClick:t[3]||(t[3]=a=>B.value="cards"),class:L(["px-2 py-1 rounded text-xs font-medium",B.value==="cards"?"bg-blue-100 text-blue-700":"bg-gray-100 text-gray-700 hover:bg-gray-200"])},t[13]||(t[13]=[e("i",{class:"fas fa-th-large mr-1"},null,-1),p(" Cards ")]),2),e("button",{onClick:t[4]||(t[4]=a=>B.value="list"),class:L(["px-2 py-1 rounded text-xs font-medium",B.value==="list"?"bg-blue-100 text-blue-700":"bg-gray-100 text-gray-700 hover:bg-gray-200"])},t[14]||(t[14]=[e("i",{class:"fas fa-list mr-1"},null,-1),p(" List ")]),2)])]),B.value==="cards"?(o(),r("div",$t,[(o(!0),r(M,null,V(Q.value,a=>{var C,c,G;return o(),r("div",{key:a.id,onClick:E=>j(a.id),class:L(["p-2 border rounded-md cursor-pointer transition-all hover:shadow-sm",$.value===a.id?"border-blue-500 bg-blue-50 ring-1 ring-blue-200":"border-gray-200 hover:border-blue-300"])},[e("div",St,[e("div",It,i(((C=a.outlet)==null?void 0:C.nama_outlet)||"Unknown Outlet"),1),e("span",{class:L([H(a.status),"text-xs px-1 py-0.5 rounded flex-shrink-0"])},i(a.status),3)]),e("div",Rt,i(a.order_number),1),e("div",Tt,[e("div",Lt,[t[15]||(t[15]=e("i",{class:"fas fa-calendar mr-1 w-3"},null,-1)),e("span",Dt,i(b(a.tanggal)),1)]),e("div",Nt,[t[16]||(t[16]=e("i",{class:"fas fa-truck mr-1 w-3"},null,-1)),e("span",Bt,"Kedatangan: "+i(a.arrival_date?b(a.arrival_date):"-"),1)])]),e("div",Ut,[t[17]||(t[17]=e("i",{class:"fas fa-user mr-1 w-3"},null,-1)),e("span",At,i(((c=a.user)==null?void 0:c.nama_lengkap)||"Unknown User"),1)]),e("div",Mt,[t[18]||(t[18]=e("i",{class:"fas fa-boxes mr-1 w-3"},null,-1)),p(" "+i(((G=a.items)==null?void 0:G.length)||0)+" items ",1)])],10,qt)}),128))])):(o(),r("div",Vt,[e("div",Ft,[e("table",Kt,[t[19]||(t[19]=e("thead",{class:"bg-gray-50"},[e("tr",null,[e("th",{class:"px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase"},"Outlet"),e("th",{class:"px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase"},"RO"),e("th",{class:"px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase"},"Tanggal"),e("th",{class:"px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase"},"Kedatangan"),e("th",{class:"px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase"},"Status"),e("th",{class:"px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase"},"Aksi")])],-1)),e("tbody",Qt,[(o(!0),r(M,null,V(Q.value,a=>{var C;return o(),r("tr",{key:a.id,class:L(["hover:bg-gray-50 cursor-pointer",$.value===a.id?"bg-blue-50":""]),onClick:c=>j(a.id)},[e("td",Gt,i(((C=a.outlet)==null?void 0:C.nama_outlet)||"Unknown Outlet"),1),e("td",Wt,i(a.order_number),1),e("td",jt,i(b(a.tanggal)),1),e("td",Et,i(a.arrival_date?b(a.arrival_date):"-"),1),e("td",Yt,[e("span",{class:L([H(a.status),"text-xs px-1.5 py-0.5 rounded"])},i(a.status),3)]),e("td",Ht,[e("button",{onClick:vt(c=>j(a.id),["stop"]),class:"text-blue-600 hover:text-blue-900 font-medium"}," Pilih ",8,Jt)])],10,zt)}),128))])])])]))])):(o(),r("div",Xt,t[20]||(t[20]=[e("i",{class:"fas fa-search text-gray-400 text-2xl mb-2"},null,-1),e("h3",{class:"text-sm font-medium text-gray-900 mb-1"},"Tidak ada RO ditemukan",-1),e("p",{class:"text-xs text-gray-500"},"Coba ubah kata kunci pencarian atau filter status",-1)]))),$.value?(o(),r("div",Zt,[t[27]||(t[27]=e("h3",{class:"text-sm font-semibold text-blue-900 mb-2"},"RO Terpilih",-1)),e("div",te,[e("div",null,[t[21]||(t[21]=e("label",{class:"block text-xs font-medium text-blue-700"},"Outlet",-1)),e("p",ee,i((l=(n=q.value)==null?void 0:n.outlet)==null?void 0:l.nama_outlet),1)]),e("div",null,[t[22]||(t[22]=e("label",{class:"block text-xs font-medium text-blue-700"},"Nomor RO",-1)),e("p",se,i((u=q.value)==null?void 0:u.order_number),1)]),e("div",null,[t[23]||(t[23]=e("label",{class:"block text-xs font-medium text-blue-700"},"Tanggal",-1)),e("p",le,i(b((h=q.value)==null?void 0:h.tanggal)),1)]),e("div",null,[t[24]||(t[24]=e("label",{class:"block text-xs font-medium text-blue-700"},"Kedatangan",-1)),e("p",ae,i((_=q.value)!=null&&_.arrival_date?b((d=q.value)==null?void 0:d.arrival_date):"-"),1)]),e("div",null,[t[25]||(t[25]=e("label",{class:"block text-xs font-medium text-blue-700"},"Status",-1)),e("p",ie,i((O=q.value)==null?void 0:O.status),1)]),e("div",null,[t[26]||(t[26]=e("label",{class:"block text-xs font-medium text-blue-700"},"Items",-1)),e("p",oe,i(((z=(x=q.value)==null?void 0:x.items)==null?void 0:z.length)||0)+" items",1)])]),e("div",{class:"mt-3 flex gap-2"},[e("button",{onClick:lt,class:"px-3 py-1 text-xs font-medium text-blue-700 bg-white border border-blue-300 rounded hover:bg-blue-50"}," Pilih RO Lain ")])])):f("",!0)]),v.value?(o(),r("div",ne,[t[39]||(t[39]=e("h2",{class:"text-sm font-semibold text-gray-700 mb-3"},"2. Pilih Warehouse Division",-1)),e("div",re,[t[36]||(t[36]=e("div",{class:"font-bold text-blue-800 text-sm mb-1"},"Detail RO",-1)),y.value?(o(),r("div",de,[e("div",null,[t[29]||(t[29]=e("b",null,"Outlet:",-1)),p(" "+i((A=y.value.outlet)==null?void 0:A.nama_outlet),1)]),y.value.warehouse_outlet&&y.value.warehouse_outlet.name?(o(),r("div",ue,[t[30]||(t[30]=e("b",null,"Warehouse Outlet:",-1)),p(" "+i(y.value.warehouse_outlet.name),1)])):f("",!0),e("div",null,[t[31]||(t[31]=e("b",null,"Tanggal:",-1)),p(" "+i(b(y.value.tanggal)),1)]),y.value.arrival_date?(o(),r("div",me,[t[32]||(t[32]=e("b",null,"Kedatangan:",-1)),p(" "+i(b(y.value.arrival_date)),1)])):f("",!0),e("div",null,[t[33]||(t[33]=e("b",null,"RO Mode:",-1)),p(" "+i(y.value.fo_mode),1)]),e("div",null,[t[34]||(t[34]=e("b",null,"Nomor:",-1)),p(" "+i(y.value.order_number),1)]),e("div",null,[t[35]||(t[35]=e("b",null,"Creator:",-1)),p(" "+i(((J=y.value.user)==null?void 0:J.nama_lengkap)||"-"),1)])])):f("",!0)]),e("div",ce,[t[38]||(t[38]=e("label",{class:"block text-xs font-medium text-gray-700 mb-1"},"Warehouse Division",-1)),T(e("select",{"onUpdate:modelValue":t[5]||(t[5]=a=>g.value=a),class:"w-full px-3 py-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500"},[t[37]||(t[37]=e("option",{value:""},"Pilih Warehouse Division",-1)),(o(!0),r(M,null,V(k.warehouseDivisions,a=>(o(),r("option",{key:a.id,value:a.id},i(a.name),9,ve))),128))],512),[[Z,g.value]])])])):f("",!0),v.value&&g.value?(o(),r("div",xe,[t[48]||(t[48]=e("h2",{class:"text-sm font-semibold text-gray-700 mb-3"},"3. Pilih Items untuk Packing",-1)),!F.value&&m.value.length===0?(o(),r("div",pe,t[40]||(t[40]=[e("p",{class:"text-sm"},"Semua item di warehouse division ini sudah di-packing.",-1)]))):f("",!0),F.value?(o(),r("div",ge,t[41]||(t[41]=[e("i",{class:"fas fa-spinner fa-spin text-blue-500 text-xl"},null,-1),e("p",{class:"mt-2 text-sm text-gray-600"},"Memuat data item...",-1)]))):f("",!0),K.value?(o(),r("div",be,i(K.value),1)):f("",!0),m.value.length?(o(),r("div",fe,[e("div",ye,[e("div",he," Total items: "+i(m.value.filter(a=>a.checked).length)+" dari "+i(m.value.length),1)]),(o(!0),r(M,null,V(st.value,(a,C)=>(o(),r("div",{key:C,class:"mb-4"},[e("div",_e,i(C),1),e("div",we,[e("table",ke,[t[43]||(t[43]=e("thead",null,[e("tr",{class:"bg-blue-50"},[e("th",{class:"py-1 px-2 text-left"},"No"),e("th",{class:"py-1 px-2 text-left"},"Pilih"),e("th",{class:"py-1 px-2 text-left"},"Nama Item"),e("th",{class:"py-1 px-2 text-left"},"Qty Order"),e("th",{class:"py-1 px-2 text-left"},"Input Qty"),e("th",{class:"py-1 px-2 text-left"},"Unit"),e("th",{class:"py-1 px-2 text-left"},"Action")])],-1)),e("tbody",null,[(o(!0),r(M,null,V(a,(c,G)=>{var E;return o(),r("tr",{key:c.id,class:"border-b"},[e("td",Oe,i(G+1),1),e("td",Ce,[T(e("input",{type:"checkbox","onUpdate:modelValue":W=>c.checked=W,class:"w-3 h-3"},null,8,Pe),[[xt,c.checked]])]),e("td",$e,i(((E=c.item)==null?void 0:E.name)||c.item_name),1),e("td",qe,i(c.qty??c.qty_order),1),e("td",Se,[T(e("input",{type:"number","onUpdate:modelValue":W=>c.input_qty=W,min:"0",step:"0.01",class:"w-16 px-1 py-0.5 text-xs border border-gray-300 rounded text-right",placeholder:"Qty"},null,8,Ie),[[Y,c.input_qty,void 0,{number:!0}]])]),e("td",Re,i(c.unit),1),e("td",Te,[e("button",{onClick:W=>nt(c),class:"text-blue-600 hover:text-blue-800 text-xs px-1 py-0.5 rounded hover:bg-blue-50",title:"Isi otomatis quantity packing sesuai quantity request"},t[42]||(t[42]=[e("i",{class:"fas fa-equals"},null,-1)]),8,Le)])])}),128))])])])]))),128))])):f("",!0),e("div",De,[e("button",{onClick:ut,disabled:!v.value||!g.value||!m.value.length,class:"flex-1 px-4 py-2 rounded bg-green-600 text-white font-semibold text-sm hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed"},t[44]||(t[44]=[e("i",{class:"fas fa-print mr-2"},null,-1),p(" Print Packing List ")]),8,Ne),e("button",{onClick:rt,disabled:!v.value||!g.value||!m.value.length||D.value,class:"flex-1 px-4 py-2 rounded bg-blue-600 text-white font-semibold text-sm hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed"},[t[46]||(t[46]=e("i",{class:"fas fa-save mr-2"},null,-1)),t[47]||(t[47]=p(" Submit Packing List ")),D.value?(o(),r("span",Ue,t[45]||(t[45]=[e("i",{class:"fas fa-spinner fa-spin"},null,-1)]))):f("",!0)],8,Be)])])):f("",!0)])]}),_:1}))}},Ye=gt(Ae,[["__scopeId","data-v-66471b78"]]);export{Ye as default};
