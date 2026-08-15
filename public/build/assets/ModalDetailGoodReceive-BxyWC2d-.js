import{a as E}from"./vendor-utils-CpgP2uT4.js";import{S as g}from"./app-BE3uu2br.js";import{J as W}from"./JsBarcode-BkAkkdZR.js";import{E as K}from"./jspdf.es.min-nFza0LNi.js";import{r as M,w as J,E as R,G as Y,B as j,D as t,H as X,N as v,F as Z,M as tt}from"./vendor-vue-BO1D6KDi.js";import"./vendor-charts-DlNwwLt1.js";const et={key:0,class:"fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-40"},at={class:"bg-white rounded-xl shadow-2xl w-full max-w-3xl p-6 relative max-h-[90vh] overflow-y-auto"},nt={class:"mb-4 grid grid-cols-2 gap-4"},it={class:"font-medium"},ot={class:"font-medium"},dt={class:"font-medium"},st={class:"font-medium"},rt={class:"w-full border text-sm"},lt={class:"border px-2 py-1"},ct={class:"border px-2 py-1"},pt={class:"border px-2 py-1"},ut={class:"border px-2 py-1"},mt={class:"border px-2 py-1 text-right"},gt={class:"border px-2 py-1 text-right"},bt={class:"border px-2 py-1"},xt={class:"flex flex-col gap-1"},yt=["onClick"],ft=["onClick"],ht=["disabled","title","onClick"],Nt={__name:"ModalDetailGoodReceive",props:{show:Boolean,gr:Object},emits:["close"],setup($,{emit:vt}){const C=$,L=M({}),P=M({});function A(o){return o?"Rp "+Number(o).toLocaleString("id-ID"):"-"}const Q=async()=>{var o;if((o=C.gr)!=null&&o.id)try{const{data:i}=await E.get(`/api/food-good-receive/${C.gr.id}/serial-summary`),e={},a={};(i||[]).forEach(d=>{e[d.good_receive_item_id]=Number(d.total||0),a[d.good_receive_item_id]=Number(d.in_use||0)}),L.value=e,P.value=a}catch{L.value={},P.value={}}},z=async o=>{var i,e,a;try{const{data:d}=await E.get(`/api/food-good-receive-items/${o.id}/serial-units`),b=(d==null?void 0:d.units)||[];if(!b.length){await g.fire("Info","Unit konversi item tidak ditemukan.","info");return}const D=b.reduce((r,u)=>(r[u.unit_id]=`${u.unit_name} (qty: ${u.converted_qty})`,r),{}),n=await g.fire({title:`Generate Serial - ${o.item_name}`,html:`Qty diterima: <b>${d.qty_received}</b> ${d.received_unit_name||""}`,input:"select",inputOptions:D,inputPlaceholder:"Pilih unit",showCancelButton:!0,confirmButtonText:"Lanjut",cancelButtonText:"Batal",inputValidator:r=>r?void 0:"Unit wajib dipilih"});if(!n.isConfirmed)return;const l=b.find(r=>Number(r.unit_id)===Number(n.value)),p=Number((l==null?void 0:l.converted_qty)??0),c=(l==null?void 0:l.unit_name)||"";let s=[];try{const{data:r}=await E.get("/api/fgr-serial/units");s=r||[]}catch{}let k=s.map(r=>`<option value="${r.id}">${r.name}</option>`).join("");const{value:f,isConfirmed:I}=await g.fire({title:"Konversi Unit (Opsional)",html:`
        <div style="text-align:left;font-size:14px;">
          <div style="margin-bottom:10px;">
            <strong>Unit terpilih:</strong> ${c}<br>
            <strong>Qty hasil konversi:</strong> ${p}
          </div>
          <div style="margin-bottom:10px;">
            <label style="font-weight:600;display:block;margin-bottom:4px;">Mode:</label>
            <div style="display:flex;gap:16px;">
              <label style="cursor:pointer;"><input type="radio" name="swal-conv-mode" value="no" checked> Tanpa Konversi</label>
              <label style="cursor:pointer;"><input type="radio" name="swal-conv-mode" value="yes"> Konversi Unit</label>
            </div>
          </div>
          <div id="swal-conv-wrapper" style="display:none;margin-bottom:10px;">
            <div style="margin-bottom:8px;">
              <label style="font-weight:600;display:block;margin-bottom:4px;">Unit Tujuan Serial:</label>
              <select id="swal-repack-unit" class="swal2-select" style="width:100%;padding:8px;border:1px solid #d1d5db;border-radius:6px;">
                <option value="">-- Pilih Unit --</option>
                ${k}
              </select>
            </div>
            <div>
              <label style="font-weight:600;display:block;margin-bottom:4px;">1 <span id="swal-target-unit-label">[unit tujuan]</span> = berapa ${c}?</label>
              <input type="number" id="swal-repack-qty" min="0.01" step="0.01" value="1" class="swal2-input" style="width:100%;margin:0;">
            </div>
          </div>
          <div style="margin-top:12px;padding:8px;background:#f3f4f6;border-radius:6px;">
            <span style="font-weight:600;">Jumlah serial:</span> <span id="swal-serial-count">${p}</span>
          </div>
          <div style="margin-top:12px;">
            <label style="font-weight:600;display:block;margin-bottom:4px;">Exp Date (Opsional):</label>
            <input type="date" id="swal-exp-date" class="swal2-input" style="width:100%;margin:0;">
          </div>
        </div>
      `,icon:"question",showCancelButton:!0,confirmButtonText:"Ya, generate",cancelButtonText:"Batal",didOpen:()=>{const r=document.querySelectorAll('input[name="swal-conv-mode"]'),u=document.getElementById("swal-conv-wrapper"),h=document.getElementById("swal-repack-unit"),x=document.getElementById("swal-repack-qty"),_=document.getElementById("swal-serial-count"),S=document.getElementById("swal-target-unit-label"),y=()=>{var w;if((((w=document.querySelector('input[name="swal-conv-mode"]:checked'))==null?void 0:w.value)||"no")==="yes"){const N=Math.max(.01,parseFloat(x.value)||1);_.textContent=Math.ceil(p/N)}else _.textContent=p};r.forEach(m=>m.addEventListener("change",w=>{u.style.display=w.target.value==="yes"?"block":"none",y()})),h.addEventListener("change",()=>{const m=h.options[h.selectedIndex];S.textContent=(m==null?void 0:m.text)||"[unit tujuan]"}),x.addEventListener("input",y)},preConfirm:()=>{var h,x,_,S;const r=((h=document.querySelector('input[name="swal-conv-mode"]:checked'))==null?void 0:h.value)||"no",u=((x=document.getElementById("swal-exp-date"))==null?void 0:x.value)||null;if(r==="yes"){const y=(_=document.getElementById("swal-repack-unit"))==null?void 0:_.value,m=parseFloat((S=document.getElementById("swal-repack-qty"))==null?void 0:S.value)||0;return y?m<=0?(g.showValidationMessage("Qty konversi harus lebih dari 0"),!1):{repack_unit_id:parseInt(y),repack_qty:m,exp_date:u}:(g.showValidationMessage("Pilih unit tujuan terlebih dahulu"),!1)}return{repack_unit_id:null,repack_qty:null,exp_date:u}}});if(!I||!f)return;const q=await E.post(`/api/food-good-receive-items/${o.id}/generate-serials`,{unit_id:Number(n.value),repack_unit_id:f.repack_unit_id,repack_qty:f.repack_qty,exp_date:f.exp_date||null});await g.fire("Berhasil",((i=q.data)==null?void 0:i.message)||"Serial berhasil dibuat.","success"),await Q()}catch(d){const b=((a=(e=d==null?void 0:d.response)==null?void 0:e.data)==null?void 0:a.message)||"Gagal generate serial.";await g.fire("Error",b,"error")}},H=async o=>{var i,e;try{const{data:a}=await E.get(`/api/food-good-receive-items/${o.id}/serials`);if(!a||!a.length){await g.fire("Info","Belum ada serial untuk item ini.","info");return}const d=n=>n!=null?parseFloat(Number(n).toFixed(4)).toString():"",b=n=>{if(!n)return"-";const l=new Date(n);return Number.isNaN(l.getTime())?n:l.toLocaleDateString("id-ID")},D=a.slice(0,200).map((n,l)=>{const p=n.repack_unit_id&&n.repack_qty?`<span style="background:#f3e8ff;color:#7c3aed;padding:1px 6px;border-radius:4px;font-size:10px;font-weight:600;">1 ${n.repack_unit_name||"?"} = ${d(n.repack_qty)} ${n.unit_name||""}</span>`:'<span style="background:#e0f2fe;color:#0369a1;padding:1px 6px;border-radius:4px;font-size:10px;">Tanpa konversi</span>';return`<tr>
            <td style="border:1px solid #ddd;padding:4px;text-align:center;">${l+1}</td>
            <td style="border:1px solid #ddd;padding:4px;">${n.serial_number}</td>
            <td style="border:1px solid #ddd;padding:4px;">${n.unit_name||"-"}</td>
            <td style="border:1px solid #ddd;padding:4px;">${p}</td>
            <td style="border:1px solid #ddd;padding:4px;">${b(n.exp_date)}</td>
            <td style="border:1px solid #ddd;padding:4px;">${n.pr_number||"-"}</td>
            <td style="border:1px solid #ddd;padding:4px;">${n.po_number||"-"}</td>
            <td style="border:1px solid #ddd;padding:4px;">${n.gr_number||"-"}</td>
            <td style="border:1px solid #ddd;padding:4px;text-align:center;">
              <button
                type="button"
                class="serial-pdf-btn"
                data-serial="${n.serial_number}"
                data-repack-unit-name="${n.repack_unit_name||""}"
                data-repack-qty="${n.repack_qty||""}"
                data-unit-name="${n.unit_name||""}"
                data-exp-date="${n.exp_date||""}"
                style="padding:2px 8px;background:#dbeafe;color:#1d4ed8;border-radius:4px;border:0;cursor:pointer;"
              >
                PDF 10x5
              </button>
            </td>
          </tr>`}).join("");await g.fire({title:`Serial - ${o.item_name}`,width:980,html:`
        <div style="display:flex;justify-content:flex-end;margin-bottom:8px;">
          <button
            id="download-all-serial-pdf-btn"
            type="button"
            style="padding:6px 10px;background:#dbeafe;color:#1d4ed8;border-radius:6px;border:0;cursor:pointer;font-size:12px;font-weight:600;"
          >
            Download All PDF (10x5cm)
          </button>
        </div>
        <div style="max-height:420px;overflow:auto;">
          <table style="width:100%;border-collapse:collapse;font-size:12px;">
            <thead>
              <tr>
                <th style="border:1px solid #ddd;padding:4px;">No</th>
                <th style="border:1px solid #ddd;padding:4px;">Serial</th>
                <th style="border:1px solid #ddd;padding:4px;">Unit</th>
                <th style="border:1px solid #ddd;padding:4px;">Konversi</th>
                <th style="border:1px solid #ddd;padding:4px;">Exp Date</th>
                <th style="border:1px solid #ddd;padding:4px;">No PR</th>
                <th style="border:1px solid #ddd;padding:4px;">No PO</th>
                <th style="border:1px solid #ddd;padding:4px;">No GR</th>
                <th style="border:1px solid #ddd;padding:4px;">Print</th>
              </tr>
            </thead>
            <tbody>${D}</tbody>
          </table>
        </div>
      `,didOpen:()=>{const n=document.getElementById("download-all-serial-pdf-btn");n&&n.addEventListener("click",()=>{var p,c,s,k;G(a.map(f=>f.serial_number),o.item_name,{repackUnitName:((p=a[0])==null?void 0:p.repack_unit_name)||null,repackQty:((c=a[0])==null?void 0:c.repack_qty)||null,unitName:((s=a[0])==null?void 0:s.unit_name)||"",expDate:((k=a[0])==null?void 0:k.exp_date)||null})}),document.querySelectorAll(".serial-pdf-btn").forEach(p=>{p.addEventListener("click",c=>{var r,u,h,x,_;const s=(r=c.target)==null?void 0:r.getAttribute("data-serial"),k=((u=c.target)==null?void 0:u.getAttribute("data-repack-unit-name"))||null,f=((h=c.target)==null?void 0:h.getAttribute("data-repack-qty"))||null,I=((x=c.target)==null?void 0:x.getAttribute("data-unit-name"))||"",q=((_=c.target)==null?void 0:_.getAttribute("data-exp-date"))||null;s&&G([s],o.item_name,{repackUnitName:k||null,repackQty:f?parseFloat(f):null,unitName:I,expDate:q||null})})})}})}catch(a){const d=((e=(i=a==null?void 0:a.response)==null?void 0:i.data)==null?void 0:e.message)||"Gagal mengambil serial.";await g.fire("Error",d,"error")}},G=(o,i,e={})=>{if(!(o!=null&&o.length))return;const a=100,d=50,b=5,D=5,n=5,l=3,p=297,c=210,s=new K({orientation:"landscape",unit:"mm",format:[p,c]}),k=d+b,f=c-n*2,I=Math.max(1,Math.floor(f/k));o.forEach((r,u)=>{const h=l*I,x=u%h;u>0&&x===0&&s.addPage([p,c],"landscape");const _=Math.floor(x/l),S=x%l,y=D+S*(a+b),m=n+_*k;s.setDrawColor(0,0,0),s.setLineWidth(.5),s.rect(y,m,a,d);const w=a-10,N=20,U=3,F=document.createElement("canvas");F.width=w*U,F.height=N*U,W(F,r,{width:1.5*U,height:N*U,displayValue:!1});const V=y+(a-w)/2;s.addImage(F,"PNG",V,m+3,w,N);let B=m+N+5;if(s.setFontSize(8),s.setFont(void 0,"bold"),s.text(`SERIAL: ${r}`,y+a/2,B,{align:"center"}),B+=4.5,s.setFontSize(9),s.setFont(void 0,"bold"),s.text(`${i||""}`,y+a/2,B,{align:"center"}),B+=3.5,e!=null&&e.repackUnitName&&(e!=null&&e.repackQty)){const T=parseFloat(Number(e.repackQty).toFixed(4)).toString();s.setFontSize(7),s.setFont(void 0,"bold"),s.text(`1 ${e.repackUnitName.toUpperCase()} = ${T} ${(e.unitName||"").toUpperCase()}`,y+a/2,B,{align:"center"}),B+=3.5}if(e!=null&&e.expDate){const T=new Date(e.expDate).toLocaleDateString("id-ID");s.setFontSize(7),s.setFont(void 0,"bold"),s.text(`EXP: ${T}`,y+a/2,B,{align:"center"})}});const q=o[0]||"serial";s.save(`${q}_labels_10x5cm.pdf`)},O=async o=>{var e,a;if((await g.fire({title:"Rollback serial?",text:"Semua serial untuk item ini akan dihapus (di GR ini).",icon:"warning",showCancelButton:!0,confirmButtonText:"Ya, rollback",cancelButtonText:"Batal",confirmButtonColor:"#d33"})).isConfirmed)try{const{data:d}=await E.delete(`/api/food-good-receive-items/${o.id}/serials`);await g.fire("Berhasil",(d==null?void 0:d.message)||"Rollback serial berhasil.","success"),await Q()}catch(d){const b=((a=(e=d==null?void 0:d.response)==null?void 0:e.data)==null?void 0:a.message)||"Gagal rollback serial.";await g.fire("Error",b,"error")}};return J(()=>{var o;return[C.show,(o=C.gr)==null?void 0:o.id]},([o])=>{o&&Q()},{immediate:!0}),(o,i)=>$.show?(j(),R("div",et,[t("div",at,[i[8]||(i[8]=t("h2",{class:"text-xl font-bold mb-4 flex items-center gap-2"},[t("i",{class:"fa-solid fa-file-lines text-blue-500"}),X(" Detail Good Receive ")],-1)),t("button",{onClick:i[0]||(i[0]=e=>o.$emit("close")),class:"absolute top-4 right-4 text-gray-400 hover:text-red-500"},i[1]||(i[1]=[t("i",{class:"fa-solid fa-xmark text-2xl"},null,-1)])),t("div",nt,[t("div",null,[i[2]||(i[2]=t("div",{class:"text-sm text-gray-500"},"Tanggal",-1)),t("div",it,v($.gr.receive_date),1)]),t("div",null,[i[3]||(i[3]=t("div",{class:"text-sm text-gray-500"},"No. PO",-1)),t("div",ot,v($.gr.po_number),1)]),t("div",null,[i[4]||(i[4]=t("div",{class:"text-sm text-gray-500"},"Supplier",-1)),t("div",dt,v($.gr.supplier_name),1)]),t("div",null,[i[5]||(i[5]=t("div",{class:"text-sm text-gray-500"},"Petugas",-1)),t("div",st,v($.gr.received_by_name),1)])]),t("div",null,[i[7]||(i[7]=t("div",{class:"font-semibold mb-2"},"Daftar Item",-1)),t("table",rt,[i[6]||(i[6]=t("thead",{class:"bg-gray-100"},[t("tr",null,[t("th",{class:"border px-2 py-1"},"Nama Item"),t("th",{class:"border px-2 py-1"},"Qty Diterima"),t("th",{class:"border px-2 py-1"},"Qty Ditolak"),t("th",{class:"border px-2 py-1"},"Unit"),t("th",{class:"border px-2 py-1"},"Harga"),t("th",{class:"border px-2 py-1"},"Total"),t("th",{class:"border px-2 py-1"},"Serial")])],-1)),t("tbody",null,[(j(!0),R(Z,null,tt($.gr.items||[],e=>(j(),R("tr",{key:e.id},[t("td",lt,v(e.item_name),1),t("td",ct,v(e.qty_received),1),t("td",pt,v(e.qty_rejected??"—"),1),t("td",ut,v(e.unit_name),1),t("td",mt,v(A(e.price)),1),t("td",gt,v(A(e.qty_received*e.price)),1),t("td",bt,[t("div",xt,[t("button",{type:"button",class:"px-2 py-1 text-xs rounded bg-blue-100 text-blue-700 hover:bg-blue-200",onClick:a=>z(e)}," Generate Serial ",8,yt),t("button",{type:"button",class:"px-2 py-1 text-xs rounded bg-gray-100 text-gray-700 hover:bg-gray-200",onClick:a=>H(e)}," Lihat Serial ("+v(L.value[e.id]||0)+") ",9,ft),t("button",{type:"button",class:"px-2 py-1 text-xs rounded bg-red-100 text-red-700 hover:bg-red-200 disabled:opacity-50 disabled:cursor-not-allowed",disabled:(P.value[e.id]||0)>0,title:(P.value[e.id]||0)>0?"Ada serial yang sudah digunakan — tidak bisa rollback.":"",onClick:a=>O(e)}," Rollback Serial ",8,ht)])])]))),128))])])])])])):Y("",!0)}};export{Nt as default};
