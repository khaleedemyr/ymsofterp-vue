import{c as v,r as _,w as H,L as i,B as r,D as t,E as w,x as K,N as u,F as g,M as f,O as A,Q,l as W,G as M}from"./vendor-vue-g7iCcTkf.js";import{N as J}from"./vue-easy-lightbox.esm.min-H9agbAmC.js";import{_ as X}from"./_plugin-vue_export-helper-DlAUqK2U.js";import"./vendor-utils-CpgP2uT4.js";const Y={class:"bg-white rounded-2xl shadow-2xl w-full max-w-2xl p-8 relative animate-fadeIn overflow-y-auto print-modal",style:{"max-height":"90vh"},id:"revenue-report-modal"},Z={class:"text-center mb-4"},G={class:"text-xs text-gray-400 mt-1"},ee={class:"mb-6"},te={class:"text-3xl font-extrabold text-blue-800 mb-4"},oe={class:"min-w-full text-sm rounded shadow mb-8"},ne={class:"bg-white border-b last:border-b-0"},se={class:"px-2 py-2 text-center"},ae=["onClick"],le={class:"px-3 py-2"},ie={class:"px-3 py-2 text-right"},re={key:0},de={colspan:"2",class:"bg-blue-50 px-6 py-2"},ue={class:"font-semibold mb-1"},ce={class:"min-w-full text-xs mb-2"},me={class:"px-2 py-1"},pe={class:"px-2 py-1 text-right"},be={class:"mb-8"},ge={key:0,class:"text-gray-400 italic"},fe={key:1},ve={class:"font-semibold text-gray-800 mb-2"},ye={class:"mb-2"},_e={class:"list-disc ml-6"},he={class:"font-bold"},xe={class:"flex flex-wrap gap-2 items-center mt-2"},ke=["src","onClick"],we={key:1,class:"italic text-gray-400"},$e={key:2,class:"text-gray-400 italic"},Ce={class:"mb-8"},Ne={key:0,class:"text-gray-400 italic"},Ee={key:1},Ie={key:0,class:"mb-3 p-3 bg-blue-50 dark:bg-blue-900/20 rounded-lg border border-blue-200 dark:border-blue-700"},Be={key:0,class:"text-sm text-black dark:text-black font-medium mb-1"},je={class:"font-bold text-black dark:text-black text-base"},Te={class:"font-semibold text-gray-800 mb-2"},Pe={class:"mb-2"},Se={class:"list-disc ml-6"},ze={class:"font-bold"},Re={class:"flex flex-wrap gap-2 items-center mt-2"},De=["src","onClick"],Ue={key:1,class:"italic text-gray-400"},Ae={key:2,class:"text-gray-400 italic"},Me={class:"mt-8 p-4 bg-blue-50 rounded-lg border-2 border-blue-200"},qe={class:"text-lg"},Fe={class:"flex justify-between items-center"},Le={class:"font-semibold"},Oe={class:"flex justify-between items-center"},Ve={class:"font-semibold"},He={class:"flex justify-between items-center text-xl font-bold text-blue-900"},Ke=["src"],Qe={__name:"RevenueReportModal",props:{tanggal:String,orders:Array,outlets:Array},setup(j){const d=j,T=v(()=>(d.orders||[]).reduce((o,e)=>o+(Number(e.grand_total)||0),0)),$=v(()=>{const e=Object.entries(N.value).find(([n])=>n&&n.toUpperCase()==="CASH");return e?e[1]:0}),C=v(()=>{const o=(b.value.retail_food||[]).reduce((n,s)=>n+(Number(s.total_amount)||0),0),e=(b.value.retail_non_food||[]).reduce((n,s)=>n+(Number(s.total_amount)||0),0);return o+e}),P=v(()=>$.value-C.value),S=v(()=>{var n;if(!d.orders||!d.orders.length)return"";const o=(n=d.orders[0])==null?void 0:n.kode_outlet;if(!o||!d.outlets)return"";const e=d.outlets.find(s=>s.qr_code===o);return e?e.name:""}),N=v(()=>{const o={};return(d.orders||[]).forEach(e=>{if(e.payments&&Array.isArray(e.payments))e.payments.forEach(n=>{const s=n.payment_code||"-",l=Number(n.amount)||0;o[s]=(o[s]||0)+l});else if(e.payment_code){const n=e.payment_code||"-",s=Number(e.amount)||0;o[n]=(o[n]||0)+s}}),o}),x=_({});function q(o){x.value[o]=!x.value[o]}const z=v(()=>{const o={};return(d.orders||[]).forEach(e=>{if(e.payments&&Array.isArray(e.payments))e.payments.forEach(n=>{const s=n.payment_code||"-";let l=n.payment_type;!l&&e.payment_type&&(l=e.payment_type),l||(l="Unknown"),l=String(l).toUpperCase();const a=Number(n.amount)||0;o[s]||(o[s]={}),o[s][l]=(o[s][l]||0)+a});else if(e.payment_code){const n=e.payment_code||"-";let s=e.payment_type;s||(s="Unknown"),s=String(s).toUpperCase();const l=Number(e.amount)||0;o[n]||(o[n]={}),o[n][s]=(o[n][s]||0)+l}}),o}),b=_({retail_food:[],retail_non_food:[]}),k=_(!1),E=_(null),F=v(()=>{if(!b.value.retail_non_food||!b.value.retail_non_food.length)return{};const o={};return b.value.retail_non_food.forEach(e=>{const n=e.category_budget_id||"no-category";o[n]||(o[n]={budget_info:e.budget_info||null,transactions:[]}),o[n].transactions.push(e)}),o}),I=_(!1),R=_([]),D=_(0),B=o=>{if(!o||!o.file_path)return null;try{return`/storage/${o.file_path}`}catch(e){return console.error("Error processing image:",e),null}};function U(o,e=0){!o||o.length===0||(R.value=o.map(n=>B(n)).filter(n=>n),D.value=e,I.value=!0)}async function L(){var n,s,l;if(console.log("fetchExpenses called",{orders:d.orders,tanggal:d.tanggal,outlets:d.outlets}),!d.orders||!d.orders.length)return;const o=(n=d.orders[0])==null?void 0:n.kode_outlet;let e=null;if(o&&d.outlets){const a=d.outlets.find(c=>c.qr_code===o);e=a?a.id:null}if(!e&&o)try{const a=await fetch("/api/outlets/report");if(a.ok){const p=(s=(await a.json()).outlets)==null?void 0:s.find(h=>h.qr_code===o);e=p?p.id:null}}catch(a){console.error("Error fetching outlets for outlet ID lookup:",a)}if(!e||!d.tanggal){console.log("fetchExpenses: missing outletId or tanggal",{outletId:e,tanggal:d.tanggal});return}k.value=!0;try{console.log("fetchExpenses: fetching",`/api/outlet-expenses?outlet_id=${encodeURIComponent(e)}&date=${encodeURIComponent(d.tanggal)}`);const a=await fetch(`/api/outlet-expenses?outlet_id=${encodeURIComponent(e)}&date=${encodeURIComponent(d.tanggal)}`);if(a.ok){const c=await a.json();console.log("fetchExpenses: response",c),console.log("fetchExpenses: retail_non_food with budget_info",(l=c.retail_non_food)==null?void 0:l.map(p=>({id:p.id,retail_number:p.retail_number,category_budget_id:p.category_budget_id,has_budget_info:!!p.budget_info,budget_info:p.budget_info}))),b.value=c}else console.log("fetchExpenses: response not ok",a.status),b.value={retail_food:[],retail_non_food:[]}}catch(a){console.error("fetchExpenses error",a),b.value={retail_food:[],retail_non_food:[]}}finally{k.value=!1}}H(()=>[d.tanggal,d.orders],L,{immediate:!0});function m(o){if(typeof o=="number")return o.toLocaleString("id-ID",{style:"currency",currency:"IDR",maximumFractionDigits:0});if(!o)return"-";const e=Number(o);return isNaN(e)?o:e.toLocaleString("id-ID",{style:"currency",currency:"IDR",maximumFractionDigits:0})}function O(){setTimeout(()=>{const o=document.getElementById("revenue-report-modal");if(!o){alert("Modal tidak ditemukan!");return}o.cloneNode(!0).querySelectorAll("button, .fa-solid").forEach(l=>l.remove());const s=window.open("","_blank","width=900,height=1200");s.document.write(`
      <html>
        <head>
          <title>Revenue Report</title>
                     <style>
             body {
               font-family: 'Segoe UI', Arial, sans-serif;
               margin: 0;
               padding: 16px 12px;
               background: #fff;
               color: #222;
               font-size: 10px;
               line-height: 1.2;
             }
             .report-title {
               font-size: 1.2rem;
               font-weight: bold;
               color: #2563eb;
               margin-bottom: 0.25rem;
               text-align: center;
             }
             .report-date {
               font-size: 0.8rem;
               color: #888;
               text-align: center;
               margin-bottom: 0.75rem;
             }
             .report-outlet {
               font-size: 0.9rem;
               color: #2563eb;
               text-align: center;
               margin-bottom: 0.5rem;
               font-weight: 600;
             }
             .summary-section {
               display: flex;
               flex-wrap: wrap;
               gap: 16px;
               margin-bottom: 1rem;
               justify-content: center;
             }
             .summary-card {
               background: #f3f6fa;
               border-radius: 6px;
               box-shadow: 0 1px 4px rgba(0,0,0,0.04);
               padding: 8px 16px;
               min-width: 120px;
               text-align: center;
             }
             .summary-label {
               font-size: 0.7rem;
               color: #666;
               margin-bottom: 0.1rem;
             }
             .summary-value {
               font-size: 1rem;
               font-weight: bold;
               color: #2563eb;
             }
             table {
               width: 100%;
               border-collapse: collapse;
               margin-bottom: 0.75rem;
               font-size: 9px;
             }
             th, td {
               padding: 4px 6px;
               border-bottom: 1px solid #e5e7eb;
             }
             th {
               background: #e0eaff;
               color: #1e293b;
               font-weight: bold;
               font-size: 9px;
             }
             .section-title {
               font-size: 0.9rem;
               font-weight: bold;
               color: #2563eb;
               margin: 1rem 0 0.25rem 0;
             }
             .expense-block {
               border: 1px solid #e5e7eb;
               border-radius: 4px;
               padding: 6px 10px;
               margin-bottom: 0.5rem;
               background: #f9fafb;
               font-size: 9px;
             }
             .expense-title {
               font-weight: bold;
               color: #222;
               font-size: 9px;
             }
             .expense-items {
               margin: 0.25rem 0 0.25rem 0.5rem;
             }
             .expense-items ul {
               margin: 0;
               padding-left: 1rem;
             }
             .expense-items li {
               margin-bottom: 0.1rem;
             }
             .expense-total {
               font-weight: bold;
               color: #2563eb;
             }
             .cash-section {
               background: #e0eaff;
               border-radius: 4px;
               padding: 8px 12px;
               margin-top: 1rem;
               font-size: 0.9rem;
             }
             .cash-row {
               display: flex;
               justify-content: space-between;
               margin-bottom: 0.25rem;
             }
             .cash-label {
               color: #222;
             }
             .cash-value {
               font-weight: bold;
             }
                           @media print {
                body { 
                  margin: 0; 
                  padding: 8px 6px;
                }
                @page {
                  margin: 0.25in;
                  size: A4;
                }
                /* Pastikan semua konten muat dalam 1 halaman */
                .section-title {
                  page-break-after: avoid;
                  page-break-inside: avoid;
                }
                .expense-block {
                  page-break-inside: avoid;
                }
                .cash-section {
                  page-break-inside: avoid;
                }
                table {
                  page-break-inside: avoid;
                }
                /* Kompres spacing lebih lanjut untuk print */
                .summary-section {
                  gap: 8px;
                  margin-bottom: 0.5rem;
                }
                .summary-card {
                  padding: 4px 8px;
                  min-width: 100px;
                }
                .expense-block {
                  padding: 4px 6px;
                  margin-bottom: 0.25rem;
                }
                .cash-section {
                  padding: 6px 8px;
                  margin-top: 0.5rem;
                }
              }
           </style>
        </head>
        <body>
          <div class="report-title">Revenue Report</div>
          ${S.value?`<div class="report-outlet">${S.value}</div>`:""}
          <div class="report-date">${d.tanggal||""}</div>
                     <!-- Summary Section -->
           <div class="summary-section">
             <div class="summary-card">
               <div class="summary-label">Total Sales</div>
               <div class="summary-value">${m(T.value)}</div>
             </div>
           </div>
                     <!-- Payment Breakdown -->
           <div class="section-title">Breakdown by Payment Method</div>
           <table>
             <thead>
               <tr>
                 <th>Metode Pembayaran</th>
                 <th>Payment Type</th>
                 <th>Total</th>
               </tr>
             </thead>
             <tbody>
               ${Object.entries(N.value).map(([l,a])=>{const c=z.value[l]||{},p=Object.entries(c);return p.length===0?`<tr>
                     <td>${l||"-"}</td>
                     <td>-</td>
                     <td style="text-align:right">${m(a)}</td>
                   </tr>`:p.map(([h,y],V)=>`
                   <tr>
                     <td>${V===0?l||"-":""}</td>
                     <td>${h||"-"}</td>
                     <td style="text-align:right">${m(y)}</td>
                   </tr>
                 `).join("")}).join("")}
             </tbody>
           </table>
          <!-- Pengeluaran Bahan Baku -->
          <div class="section-title">Pengeluaran Bahan Baku</div>
          ${(b.value.retail_food||[]).length===0?'<div style="color:#888">Tidak ada pengeluaran bahan baku.</div>':""}
                    ${(b.value.retail_food||[]).map(l=>`
            <div class="expense-block">
              <div class="expense-title">No: ${l.retail_number}</div>
              <div class="expense-items">
                <ul>
                  ${(l.items||[]).map(a=>`
                    <li>${a.item_name} - ${a.qty} x ${m(a.harga_barang)} = <span class="expense-total">${m(a.subtotal)}</span></li>
                    `).join("")}
                </ul>
              </div>
            </div>
          `).join("")}
          <!-- Pengeluaran Non Bahan Baku -->
          <div class="section-title">Pengeluaran Non Bahan Baku</div>
          ${(b.value.retail_non_food||[]).length===0?'<div style="color:#888">Tidak ada pengeluaran non bahan baku.</div>':""}
                    ${(()=>{const l={};return(b.value.retail_non_food||[]).forEach(a=>{const c=a.category_budget_id||"no-category";l[c]||(l[c]={budget_info:a.budget_info||null,transactions:[]}),l[c].transactions.push(a)}),Object.entries(l).map(([a,c])=>{let p="";return c.budget_info&&(p+=`
                            <div style="margin-bottom: 0.5rem; padding: 0.5rem; background: #e0eaff; border-radius: 4px; font-size: 9px;">
                              ${c.budget_info.division_name?`<div style="font-size: 8px; color: #000000; margin-bottom: 0.2rem; font-weight: 500;">${c.budget_info.division_name}</div>`:""}
                              <div style="font-weight: bold; color: #000000; font-size: 10px;">${c.budget_info.category_name||"Category "+a}</div>
                            </div>
                          `),c.transactions.forEach(h=>{p+=`
                            <div class="expense-block">
                              <div class="expense-title">No: ${h.retail_number}</div>
                              <div class="expense-items">
                                <ul>
                                  ${(h.items||[]).map(y=>`
                                    <li>${y.item_name} - ${y.qty} ${y.unit} x ${m(y.price)} = <span class="expense-total">${m(y.subtotal)}</span></li>
                                  `).join("")}
                                </ul>
                              </div>
                            </div>
                          `}),p}).join("")})()}
           <!-- Summary Section -->
           <div class="section-title">Summary</div>
           <div class="cash-section">
             <div class="cash-row"><span class="cash-label">Total Cash:</span><span class="cash-value">${m($.value)}</span></div>
             <div class="cash-row"><span class="cash-label">Total Pengeluaran:</span><span class="cash-value">${m(C.value)}</span></div>
             <div class="cash-row" style="font-size:1rem;font-weight:bold;"><span class="cash-label">Nilai Setor Cash:</span><span class="cash-value">${m(P.value)}</span></div>
           </div>
        </body>
      </html>
    `),s.document.close(),setTimeout(()=>{s.focus(),s.print(),s.close()},500)},100)}return(o,e)=>(r(),i("div",{class:"fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-40",onClick:e[5]||(e[5]=A(n=>o.$emit("close"),["self"]))},[t("div",Y,[t("button",{onClick:e[0]||(e[0]=n=>o.$emit("close")),class:"absolute top-4 right-4 text-gray-400 hover:text-red-500 text-2xl font-bold"},"×"),t("button",{onClick:O,class:"absolute top-4 right-16 text-gray-400 hover:text-blue-600 text-2xl font-bold",title:"Print PDF"},e[6]||(e[6]=[t("i",{class:"fa-solid fa-print"},null,-1)])),t("div",Z,[e[7]||(e[7]=t("div",{class:"text-xl font-bold text-gray-800"},"Revenue Report",-1)),t("div",G,u(j.tanggal),1)]),e[24]||(e[24]=t("div",{class:"border-b border-gray-200 mb-4"},null,-1)),t("div",ee,[e[8]||(e[8]=t("div",{class:"font-bold text-blue-700 mb-2"},"Total Sales",-1)),t("div",te,u(m(T.value)),1)]),t("div",null,[e[12]||(e[12]=t("div",{class:"font-bold text-green-700 mb-2"},"Breakdown by Payment Method",-1)),t("table",oe,[e[11]||(e[11]=t("thead",null,[t("tr",{class:"bg-green-100 text-green-900"},[t("th",{class:"px-2 py-2 w-8"}),t("th",{class:"px-3 py-2"},"Metode Pembayaran"),t("th",{class:"px-3 py-2 text-right"},"Total")])],-1)),t("tbody",null,[(r(!0),i(g,null,f(N.value,(n,s)=>(r(),i(g,{key:s},[t("tr",ne,[t("td",se,[t("button",{onClick:l=>q(s),class:"focus:outline-none"},[t("i",{class:W(x.value[s]?"fa-solid fa-chevron-down":"fa-solid fa-chevron-right")},null,2)],8,ae)]),t("td",le,u(s||"-"),1),t("td",ie,u(m(n)),1)]),x.value[s]?(r(),i("tr",re,[e[10]||(e[10]=t("td",null,null,-1)),t("td",de,[t("div",ue,"Detail "+u(s),1),t("table",ce,[e[9]||(e[9]=t("thead",null,[t("tr",{class:"bg-blue-100 text-blue-900"},[t("th",{class:"px-2 py-1"},"Payment Type"),t("th",{class:"px-2 py-1 text-right"},"Total")])],-1)),t("tbody",null,[(r(!0),i(g,null,f(z.value[s],(l,a)=>(r(),i("tr",{key:a},[t("td",me,u(a||"-"),1),t("td",pe,u(m(l)),1)]))),128))])])])])):w("",!0)],64))),128))])])]),t("div",be,[e[15]||(e[15]=t("div",{class:"font-bold text-red-700 mb-2"},"Pengeluaran Bahan Baku",-1)),k.value?(r(),i("div",ge,"Loading...")):b.value.retail_food&&b.value.retail_food.length?(r(),i("div",fe,[(r(!0),i(g,null,f(b.value.retail_food,n=>(r(),i("div",{key:"rf-"+n.id,class:"mb-3 border rounded-lg p-3"},[t("div",ve,"No: "+u(n.retail_number),1),t("div",ye,[e[13]||(e[13]=t("span",{class:"font-semibold"},"Items:",-1)),t("ul",_e,[(r(!0),i(g,null,f(n.items,s=>(r(),i("li",{key:s.id},[M(u(s.item_name)+" - "+u(s.qty)+" x "+u(m(s.harga_barang))+" = ",1),t("span",he,u(m(s.subtotal)),1)]))),128))])]),t("div",xe,[e[14]||(e[14]=t("span",{class:"font-semibold"},"Invoice:",-1)),n.invoices.length?(r(!0),i(g,{key:0},f(n.invoices,(s,l)=>(r(),i("img",{key:l,src:B(s),alt:"Invoice",class:"w-20 h-20 object-cover rounded shadow cursor-pointer",onClick:a=>U(n.invoices,l),onError:e[1]||(e[1]=a=>a.target.style.display="none")},null,40,ke))),128)):(r(),i("span",we,"no image available"))])]))),128))])):(r(),i("div",$e,"Tidak ada pengeluaran bahan baku."))]),t("div",Ce,[e[18]||(e[18]=t("div",{class:"font-bold text-purple-700 mb-2"},"Pengeluaran Non Bahan Baku",-1)),k.value?(r(),i("div",Ne,"Loading...")):b.value.retail_non_food&&b.value.retail_non_food.length?(r(),i("div",Ee,[(r(!0),i(g,null,f(F.value,(n,s)=>(r(),i("div",{key:"category-"+s,class:"mb-4"},[n.budget_info?(r(),i("div",Ie,[n.budget_info.division_name?(r(),i("div",Be,u(n.budget_info.division_name),1)):w("",!0),t("div",je,u(n.budget_info.category_name||"Category "+s),1)])):w("",!0),(r(!0),i(g,null,f(n.transactions,l=>(r(),i("div",{key:"rnf-"+l.id,class:"mb-3 border rounded-lg p-3"},[t("div",Te,"No: "+u(l.retail_number),1),t("div",Pe,[e[16]||(e[16]=t("span",{class:"font-semibold"},"Items:",-1)),t("ul",Se,[(r(!0),i(g,null,f(l.items,a=>(r(),i("li",{key:a.id},[M(u(a.item_name)+" - "+u(a.qty)+" "+u(a.unit)+" x "+u(m(a.price))+" = ",1),t("span",ze,u(m(a.subtotal)),1)]))),128))])]),t("div",Re,[e[17]||(e[17]=t("span",{class:"font-semibold"},"Invoice:",-1)),l.invoices.length?(r(!0),i(g,{key:0},f(l.invoices,(a,c)=>(r(),i("img",{key:c,src:B(a),alt:"Invoice",class:"w-20 h-20 object-cover rounded shadow cursor-pointer",onClick:p=>U(l.invoices,c),onError:e[2]||(e[2]=p=>p.target.style.display="none")},null,40,De))),128)):(r(),i("span",Ue,"no image available"))])]))),128))]))),128))])):(r(),i("div",Ae,"Tidak ada pengeluaran non bahan baku."))]),t("div",Me,[e[23]||(e[23]=t("div",{class:"font-bold text-blue-800 mb-2"},"Nilai Setor Cash",-1)),t("div",qe,[t("div",Fe,[e[19]||(e[19]=t("span",null,"Total Cash:",-1)),t("span",Le,u(m($.value)),1)]),t("div",Oe,[e[20]||(e[20]=t("span",null,"Total Pengeluaran:",-1)),t("span",Ve,u(m(C.value)),1)]),e[22]||(e[22]=t("div",{class:"border-t border-blue-300 my-2"},null,-1)),t("div",He,[e[21]||(e[21]=t("span",null,"Nilai Setor Cash:",-1)),t("span",null,u(m(P.value)),1)])])]),E.value?(r(),i("div",{key:0,class:"fixed inset-0 z-60 flex items-center justify-center bg-black bg-opacity-70",onClick:e[3]||(e[3]=A(n=>E.value=null,["self"]))},[t("img",{src:E.value,class:"max-w-full max-h-[80vh] rounded shadow-2xl border-4 border-white"},null,8,Ke)])):w("",!0),K(Q(J),{visible:I.value,imgs:R.value,index:D.value,onHide:e[4]||(e[4]=n=>I.value=!1)},null,8,["visible","imgs","index"])])]))}},Ze=X(Qe,[["__scopeId","data-v-7d7f7f38"]]);export{Ze as default};
