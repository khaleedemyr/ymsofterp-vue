import{a as E}from"./vendor-utils-CpgP2uT4.js";import{S as g}from"./app--Mn4MuI9.js";import{J as W}from"./JsBarcode-BkAkkdZR.js";import{E as K}from"./jspdf.es.min-Dwx5WgdL.js";import{r as M,w as J,E as R,G as Y,B as j,D as e,H as X,N as k,F as Z,M as tt}from"./vendor-vue-BO1D6KDi.js";import"./vendor-charts-DlNwwLt1.js";const et={key:0,class:"fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-40"},at={class:"bg-white rounded-xl shadow-2xl w-full max-w-2xl p-6 relative max-h-[90vh] overflow-y-auto"},nt={class:"mb-4 grid grid-cols-2 gap-4"},it={class:"font-medium"},ot={class:"font-medium"},dt={class:"font-medium"},st={class:"font-medium"},rt={class:"w-full border text-sm"},lt={class:"border px-2 py-1"},ct={class:"border px-2 py-1"},pt={class:"border px-2 py-1"},ut={class:"border px-2 py-1 text-right"},mt={class:"border px-2 py-1 text-right"},gt={class:"border px-2 py-1"},bt={class:"flex flex-col gap-1"},xt=["onClick"],yt=["onClick"],ft=["disabled","title","onClick"],St={__name:"ModalDetailGoodReceive",props:{show:Boolean,gr:Object},emits:["close"],setup($,{emit:ht}){const C=$,L=M({}),P=M({});function A(o){return o?"Rp "+Number(o).toLocaleString("id-ID"):"-"}const Q=async()=>{var o;if((o=C.gr)!=null&&o.id)try{const{data:i}=await E.get(`/api/food-good-receive/${C.gr.id}/serial-summary`),t={},a={};(i||[]).forEach(d=>{t[d.good_receive_item_id]=Number(d.total||0),a[d.good_receive_item_id]=Number(d.in_use||0)}),L.value=t,P.value=a}catch{L.value={},P.value={}}},z=async o=>{var i,t,a;try{const{data:d}=await E.get(`/api/food-good-receive-items/${o.id}/serial-units`),b=(d==null?void 0:d.units)||[];if(!b.length){await g.fire("Info","Unit konversi item tidak ditemukan.","info");return}const D=b.reduce((r,u)=>(r[u.unit_id]=`${u.unit_name} (qty: ${u.converted_qty})`,r),{}),n=await g.fire({title:`Generate Serial - ${o.item_name}`,html:`Qty diterima: <b>${d.qty_received}</b> ${d.received_unit_name||""}`,input:"select",inputOptions:D,inputPlaceholder:"Pilih unit",showCancelButton:!0,confirmButtonText:"Lanjut",cancelButtonText:"Batal",inputValidator:r=>r?void 0:"Unit wajib dipilih"});if(!n.isConfirmed)return;const l=b.find(r=>Number(r.unit_id)===Number(n.value)),p=Number((l==null?void 0:l.converted_qty)??0),c=(l==null?void 0:l.unit_name)||"";let s=[];try{const{data:r}=await E.get("/api/fgr-serial/units");s=r||[]}catch{}let _=s.map(r=>`<option value="${r.id}">${r.name}</option>`).join("");const{value:f,isConfirmed:I}=await g.fire({title:"Konversi Unit (Opsional)",html:`
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
                ${_}
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
      `,icon:"question",showCancelButton:!0,confirmButtonText:"Ya, generate",cancelButtonText:"Batal",didOpen:()=>{const r=document.querySelectorAll('input[name="swal-conv-mode"]'),u=document.getElementById("swal-conv-wrapper"),h=document.getElementById("swal-repack-unit"),x=document.getElementById("swal-repack-qty"),v=document.getElementById("swal-serial-count"),S=document.getElementById("swal-target-unit-label"),y=()=>{var w;if((((w=document.querySelector('input[name="swal-conv-mode"]:checked'))==null?void 0:w.value)||"no")==="yes"){const N=Math.max(.01,parseFloat(x.value)||1);v.textContent=Math.ceil(p/N)}else v.textContent=p};r.forEach(m=>m.addEventListener("change",w=>{u.style.display=w.target.value==="yes"?"block":"none",y()})),h.addEventListener("change",()=>{const m=h.options[h.selectedIndex];S.textContent=(m==null?void 0:m.text)||"[unit tujuan]"}),x.addEventListener("input",y)},preConfirm:()=>{var h,x,v,S;const r=((h=document.querySelector('input[name="swal-conv-mode"]:checked'))==null?void 0:h.value)||"no",u=((x=document.getElementById("swal-exp-date"))==null?void 0:x.value)||null;if(r==="yes"){const y=(v=document.getElementById("swal-repack-unit"))==null?void 0:v.value,m=parseFloat((S=document.getElementById("swal-repack-qty"))==null?void 0:S.value)||0;return y?m<=0?(g.showValidationMessage("Qty konversi harus lebih dari 0"),!1):{repack_unit_id:parseInt(y),repack_qty:m,exp_date:u}:(g.showValidationMessage("Pilih unit tujuan terlebih dahulu"),!1)}return{repack_unit_id:null,repack_qty:null,exp_date:u}}});if(!I||!f)return;const q=await E.post(`/api/food-good-receive-items/${o.id}/generate-serials`,{unit_id:Number(n.value),repack_unit_id:f.repack_unit_id,repack_qty:f.repack_qty,exp_date:f.exp_date||null});await g.fire("Berhasil",((i=q.data)==null?void 0:i.message)||"Serial berhasil dibuat.","success"),await Q()}catch(d){const b=((a=(t=d==null?void 0:d.response)==null?void 0:t.data)==null?void 0:a.message)||"Gagal generate serial.";await g.fire("Error",b,"error")}},H=async o=>{var i,t;try{const{data:a}=await E.get(`/api/food-good-receive-items/${o.id}/serials`);if(!a||!a.length){await g.fire("Info","Belum ada serial untuk item ini.","info");return}const d=n=>n!=null?parseFloat(Number(n).toFixed(4)).toString():"",b=n=>{if(!n)return"-";const l=new Date(n);return Number.isNaN(l.getTime())?n:l.toLocaleDateString("id-ID")},D=a.slice(0,200).map((n,l)=>{const p=n.repack_unit_id&&n.repack_qty?`<span style="background:#f3e8ff;color:#7c3aed;padding:1px 6px;border-radius:4px;font-size:10px;font-weight:600;">1 ${n.repack_unit_name||"?"} = ${d(n.repack_qty)} ${n.unit_name||""}</span>`:'<span style="background:#e0f2fe;color:#0369a1;padding:1px 6px;border-radius:4px;font-size:10px;">Tanpa konversi</span>';return`<tr>
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
      `,didOpen:()=>{const n=document.getElementById("download-all-serial-pdf-btn");n&&n.addEventListener("click",()=>{var p,c,s,_;G(a.map(f=>f.serial_number),o.item_name,{repackUnitName:((p=a[0])==null?void 0:p.repack_unit_name)||null,repackQty:((c=a[0])==null?void 0:c.repack_qty)||null,unitName:((s=a[0])==null?void 0:s.unit_name)||"",expDate:((_=a[0])==null?void 0:_.exp_date)||null})}),document.querySelectorAll(".serial-pdf-btn").forEach(p=>{p.addEventListener("click",c=>{var r,u,h,x,v;const s=(r=c.target)==null?void 0:r.getAttribute("data-serial"),_=((u=c.target)==null?void 0:u.getAttribute("data-repack-unit-name"))||null,f=((h=c.target)==null?void 0:h.getAttribute("data-repack-qty"))||null,I=((x=c.target)==null?void 0:x.getAttribute("data-unit-name"))||"",q=((v=c.target)==null?void 0:v.getAttribute("data-exp-date"))||null;s&&G([s],o.item_name,{repackUnitName:_||null,repackQty:f?parseFloat(f):null,unitName:I,expDate:q||null})})})}})}catch(a){const d=((t=(i=a==null?void 0:a.response)==null?void 0:i.data)==null?void 0:t.message)||"Gagal mengambil serial.";await g.fire("Error",d,"error")}},G=(o,i,t={})=>{if(!(o!=null&&o.length))return;const a=100,d=50,b=5,D=5,n=5,l=3,p=297,c=210,s=new K({orientation:"landscape",unit:"mm",format:[p,c]}),_=d+b,f=c-n*2,I=Math.max(1,Math.floor(f/_));o.forEach((r,u)=>{const h=l*I,x=u%h;u>0&&x===0&&s.addPage([p,c],"landscape");const v=Math.floor(x/l),S=x%l,y=D+S*(a+b),m=n+v*_;s.setDrawColor(0,0,0),s.setLineWidth(.5),s.rect(y,m,a,d);const w=a-10,N=20,U=3,F=document.createElement("canvas");F.width=w*U,F.height=N*U,W(F,r,{width:1.5*U,height:N*U,displayValue:!1});const V=y+(a-w)/2;s.addImage(F,"PNG",V,m+3,w,N);let B=m+N+5;if(s.setFontSize(8),s.setFont(void 0,"bold"),s.text(`SERIAL: ${r}`,y+a/2,B,{align:"center"}),B+=4.5,s.setFontSize(9),s.setFont(void 0,"bold"),s.text(`${i||""}`,y+a/2,B,{align:"center"}),B+=3.5,t!=null&&t.repackUnitName&&(t!=null&&t.repackQty)){const T=parseFloat(Number(t.repackQty).toFixed(4)).toString();s.setFontSize(7),s.setFont(void 0,"bold"),s.text(`1 ${t.repackUnitName.toUpperCase()} = ${T} ${(t.unitName||"").toUpperCase()}`,y+a/2,B,{align:"center"}),B+=3.5}if(t!=null&&t.expDate){const T=new Date(t.expDate).toLocaleDateString("id-ID");s.setFontSize(7),s.setFont(void 0,"bold"),s.text(`EXP: ${T}`,y+a/2,B,{align:"center"})}});const q=o[0]||"serial";s.save(`${q}_labels_10x5cm.pdf`)},O=async o=>{var t,a;if((await g.fire({title:"Rollback serial?",text:"Semua serial untuk item ini akan dihapus (di GR ini).",icon:"warning",showCancelButton:!0,confirmButtonText:"Ya, rollback",cancelButtonText:"Batal",confirmButtonColor:"#d33"})).isConfirmed)try{const{data:d}=await E.delete(`/api/food-good-receive-items/${o.id}/serials`);await g.fire("Berhasil",(d==null?void 0:d.message)||"Rollback serial berhasil.","success"),await Q()}catch(d){const b=((a=(t=d==null?void 0:d.response)==null?void 0:t.data)==null?void 0:a.message)||"Gagal rollback serial.";await g.fire("Error",b,"error")}};return J(()=>{var o;return[C.show,(o=C.gr)==null?void 0:o.id]},([o])=>{o&&Q()},{immediate:!0}),(o,i)=>$.show?(j(),R("div",et,[e("div",at,[i[8]||(i[8]=e("h2",{class:"text-xl font-bold mb-4 flex items-center gap-2"},[e("i",{class:"fa-solid fa-file-lines text-blue-500"}),X(" Detail Good Receive ")],-1)),e("button",{onClick:i[0]||(i[0]=t=>o.$emit("close")),class:"absolute top-4 right-4 text-gray-400 hover:text-red-500"},i[1]||(i[1]=[e("i",{class:"fa-solid fa-xmark text-2xl"},null,-1)])),e("div",nt,[e("div",null,[i[2]||(i[2]=e("div",{class:"text-sm text-gray-500"},"Tanggal",-1)),e("div",it,k($.gr.receive_date),1)]),e("div",null,[i[3]||(i[3]=e("div",{class:"text-sm text-gray-500"},"No. PO",-1)),e("div",ot,k($.gr.po_number),1)]),e("div",null,[i[4]||(i[4]=e("div",{class:"text-sm text-gray-500"},"Supplier",-1)),e("div",dt,k($.gr.supplier_name),1)]),e("div",null,[i[5]||(i[5]=e("div",{class:"text-sm text-gray-500"},"Petugas",-1)),e("div",st,k($.gr.received_by_name),1)])]),e("div",null,[i[7]||(i[7]=e("div",{class:"font-semibold mb-2"},"Daftar Item",-1)),e("table",rt,[i[6]||(i[6]=e("thead",{class:"bg-gray-100"},[e("tr",null,[e("th",{class:"border px-2 py-1"},"Nama Item"),e("th",{class:"border px-2 py-1"},"Qty Diterima"),e("th",{class:"border px-2 py-1"},"Unit"),e("th",{class:"border px-2 py-1"},"Harga"),e("th",{class:"border px-2 py-1"},"Total"),e("th",{class:"border px-2 py-1"},"Serial")])],-1)),e("tbody",null,[(j(!0),R(Z,null,tt($.gr.items||[],t=>(j(),R("tr",{key:t.id},[e("td",lt,k(t.item_name),1),e("td",ct,k(t.qty_received),1),e("td",pt,k(t.unit_name),1),e("td",ut,k(A(t.price)),1),e("td",mt,k(A(t.qty_received*t.price)),1),e("td",gt,[e("div",bt,[e("button",{type:"button",class:"px-2 py-1 text-xs rounded bg-blue-100 text-blue-700 hover:bg-blue-200",onClick:a=>z(t)}," Generate Serial ",8,xt),e("button",{type:"button",class:"px-2 py-1 text-xs rounded bg-gray-100 text-gray-700 hover:bg-gray-200",onClick:a=>H(t)}," Lihat Serial ("+k(L.value[t.id]||0)+") ",9,yt),e("button",{type:"button",class:"px-2 py-1 text-xs rounded bg-red-100 text-red-700 hover:bg-red-200 disabled:opacity-50 disabled:cursor-not-allowed",disabled:(P.value[t.id]||0)>0,title:(P.value[t.id]||0)>0?"Ada serial yang sudah digunakan — tidak bisa rollback.":"",onClick:a=>O(t)}," Rollback Serial ",8,ft)])])]))),128))])])])])])):Y("",!0)}};export{St as default};
