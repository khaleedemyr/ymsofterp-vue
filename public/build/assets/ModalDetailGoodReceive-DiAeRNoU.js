import{a as I}from"./vendor-utils-CpgP2uT4.js";import{S as u}from"./app-CKLKNGnw.js";import{J as V}from"./JsBarcode-I3NjN29V.js";import{E as O}from"./jspdf.es.min-DrkANVP3.js";import{r as W,w as J,I as D,N as K,B as R,D as t,E as Y,L as y,F as X,J as Z}from"./vendor-vue-DqGXf3PX.js";import"./vendor-charts-B0Jx1XF9.js";const tt={key:0,class:"fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-40"},et={class:"bg-white rounded-xl shadow-2xl w-full max-w-2xl p-6 relative max-h-[90vh] overflow-y-auto"},at={class:"mb-4 grid grid-cols-2 gap-4"},nt={class:"font-medium"},it={class:"font-medium"},ot={class:"font-medium"},st={class:"font-medium"},rt={class:"w-full border text-sm"},dt={class:"border px-2 py-1"},lt={class:"border px-2 py-1"},ct={class:"border px-2 py-1"},pt={class:"border px-2 py-1 text-right"},ut={class:"border px-2 py-1 text-right"},mt={class:"border px-2 py-1"},gt={class:"flex flex-col gap-1"},bt=["onClick"],yt=["onClick"],xt=["onClick"],Bt={__name:"ModalDetailGoodReceive",props:{show:Boolean,gr:Object},emits:["close"],setup(_,{emit:ft}){const P=_,L=W({});function T(i){return i?"Rp "+Number(i).toLocaleString("id-ID"):"-"}const Q=async()=>{var i;if((i=P.gr)!=null&&i.id)try{const{data:n}=await I.get(`/api/food-good-receive/${P.gr.id}/serial-summary`),e={};(n||[]).forEach(a=>{e[a.good_receive_item_id]=Number(a.total||0)}),L.value=e}catch{L.value={}}},G=async i=>{var n,e,a;try{const{data:o}=await I.get(`/api/food-good-receive-items/${i.id}/serial-units`),m=(o==null?void 0:o.units)||[];if(!m.length){await u.fire("Info","Unit konversi item tidak ditemukan.","info");return}const s=m.reduce((d,p)=>(d[p.unit_id]=`${p.unit_name} (qty: ${p.converted_qty})`,d),{}),x=await u.fire({title:`Generate Serial - ${i.item_name}`,html:`Qty diterima: <b>${o.qty_received}</b> ${o.received_unit_name||""}`,input:"select",inputOptions:s,inputPlaceholder:"Pilih unit",showCancelButton:!0,confirmButtonText:"Lanjut",cancelButtonText:"Batal",inputValidator:d=>d?void 0:"Unit wajib dipilih"});if(!x.isConfirmed)return;const l=m.find(d=>Number(d.unit_id)===Number(x.value)),c=Number((l==null?void 0:l.converted_qty)??0),g=(l==null?void 0:l.unit_name)||"";let r=[];try{const{data:d}=await I.get("/api/fgr-serial/units");r=d||[]}catch{}let w=r.map(d=>`<option value="${d.id}">${d.name}</option>`).join("");const{value:$,isConfirmed:S}=await u.fire({title:"Konversi Unit (Opsional)",html:`
        <div style="text-align:left;font-size:14px;">
          <div style="margin-bottom:10px;">
            <strong>Unit terpilih:</strong> ${g}<br>
            <strong>Qty hasil konversi:</strong> ${c}
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
                ${w}
              </select>
            </div>
            <div>
              <label style="font-weight:600;display:block;margin-bottom:4px;">1 <span id="swal-target-unit-label">[unit tujuan]</span> = berapa ${g}?</label>
              <input type="number" id="swal-repack-qty" min="0.01" step="0.01" value="1" class="swal2-input" style="width:100%;margin:0;">
            </div>
          </div>
          <div style="margin-top:12px;padding:8px;background:#f3f4f6;border-radius:6px;">
            <span style="font-weight:600;">Jumlah serial:</span> <span id="swal-serial-count">${c}</span>
          </div>
        </div>
      `,icon:"question",showCancelButton:!0,confirmButtonText:"Ya, generate",cancelButtonText:"Batal",didOpen:()=>{const d=document.querySelectorAll('input[name="swal-conv-mode"]'),p=document.getElementById("swal-conv-wrapper"),h=document.getElementById("swal-repack-unit"),f=document.getElementById("swal-repack-qty"),B=document.getElementById("swal-serial-count"),q=document.getElementById("swal-target-unit-label"),v=()=>{var k;if((((k=document.querySelector('input[name="swal-conv-mode"]:checked'))==null?void 0:k.value)||"no")==="yes"){const E=Math.max(.01,parseFloat(f.value)||1);B.textContent=Math.ceil(c/E)}else B.textContent=c};d.forEach(b=>b.addEventListener("change",k=>{p.style.display=k.target.value==="yes"?"block":"none",v()})),h.addEventListener("change",()=>{const b=h.options[h.selectedIndex];q.textContent=(b==null?void 0:b.text)||"[unit tujuan]"}),f.addEventListener("input",v)},preConfirm:()=>{var p,h,f;if((((p=document.querySelector('input[name="swal-conv-mode"]:checked'))==null?void 0:p.value)||"no")==="yes"){const B=(h=document.getElementById("swal-repack-unit"))==null?void 0:h.value,q=parseFloat((f=document.getElementById("swal-repack-qty"))==null?void 0:f.value)||0;return B?q<=0?(u.showValidationMessage("Qty konversi harus lebih dari 0"),!1):{repack_unit_id:parseInt(B),repack_qty:q}:(u.showValidationMessage("Pilih unit tujuan terlebih dahulu"),!1)}return{repack_unit_id:null,repack_qty:null}}});if(!S||!$)return;const N=await I.post(`/api/food-good-receive-items/${i.id}/generate-serials`,{unit_id:Number(x.value),repack_unit_id:$.repack_unit_id,repack_qty:$.repack_qty});await u.fire("Berhasil",((n=N.data)==null?void 0:n.message)||"Serial berhasil dibuat.","success"),await Q()}catch(o){const m=((a=(e=o==null?void 0:o.response)==null?void 0:e.data)==null?void 0:a.message)||"Gagal generate serial.";await u.fire("Error",m,"error")}},z=async i=>{var n,e;try{const{data:a}=await I.get(`/api/food-good-receive-items/${i.id}/serials`);if(!a||!a.length){await u.fire("Info","Belum ada serial untuk item ini.","info");return}const o=s=>s!=null?parseFloat(Number(s).toFixed(4)).toString():"",m=a.slice(0,200).map((s,x)=>{const l=s.repack_unit_id&&s.repack_qty?`<span style="background:#f3e8ff;color:#7c3aed;padding:1px 6px;border-radius:4px;font-size:10px;font-weight:600;">1 ${s.repack_unit_name||"?"} = ${o(s.repack_qty)} ${s.unit_name||""}</span>`:'<span style="background:#e0f2fe;color:#0369a1;padding:1px 6px;border-radius:4px;font-size:10px;">Tanpa konversi</span>';return`<tr>
            <td style="border:1px solid #ddd;padding:4px;text-align:center;">${x+1}</td>
            <td style="border:1px solid #ddd;padding:4px;">${s.serial_number}</td>
            <td style="border:1px solid #ddd;padding:4px;">${s.unit_name||"-"}</td>
            <td style="border:1px solid #ddd;padding:4px;">${l}</td>
            <td style="border:1px solid #ddd;padding:4px;">${s.pr_number||"-"}</td>
            <td style="border:1px solid #ddd;padding:4px;">${s.po_number||"-"}</td>
            <td style="border:1px solid #ddd;padding:4px;">${s.gr_number||"-"}</td>
            <td style="border:1px solid #ddd;padding:4px;text-align:center;">
              <button
                type="button"
                class="serial-pdf-btn"
                data-serial="${s.serial_number}"
                data-repack-unit-name="${s.repack_unit_name||""}"
                data-repack-qty="${s.repack_qty||""}"
                data-unit-name="${s.unit_name||""}"
                style="padding:2px 8px;background:#dbeafe;color:#1d4ed8;border-radius:4px;border:0;cursor:pointer;"
              >
                PDF 10x5
              </button>
            </td>
          </tr>`}).join("");await u.fire({title:`Serial - ${i.item_name}`,width:980,html:`
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
                <th style="border:1px solid #ddd;padding:4px;">No PR</th>
                <th style="border:1px solid #ddd;padding:4px;">No PO</th>
                <th style="border:1px solid #ddd;padding:4px;">No GR</th>
                <th style="border:1px solid #ddd;padding:4px;">Print</th>
              </tr>
            </thead>
            <tbody>${m}</tbody>
          </table>
        </div>
      `,didOpen:()=>{const s=document.getElementById("download-all-serial-pdf-btn");s&&s.addEventListener("click",()=>{var l,c,g;j(a.map(r=>r.serial_number),i.item_name,{repackUnitName:((l=a[0])==null?void 0:l.repack_unit_name)||null,repackQty:((c=a[0])==null?void 0:c.repack_qty)||null,unitName:((g=a[0])==null?void 0:g.unit_name)||""})}),document.querySelectorAll(".serial-pdf-btn").forEach(l=>{l.addEventListener("click",c=>{var S,N,d,p;const g=(S=c.target)==null?void 0:S.getAttribute("data-serial"),r=((N=c.target)==null?void 0:N.getAttribute("data-repack-unit-name"))||null,w=((d=c.target)==null?void 0:d.getAttribute("data-repack-qty"))||null,$=((p=c.target)==null?void 0:p.getAttribute("data-unit-name"))||"";g&&j([g],i.item_name,{repackUnitName:r||null,repackQty:w?parseFloat(w):null,unitName:$})})})}})}catch(a){const o=((e=(n=a==null?void 0:a.response)==null?void 0:n.data)==null?void 0:e.message)||"Gagal mengambil serial.";await u.fire("Error",o,"error")}},j=(i,n,e={})=>{if(!(i!=null&&i.length))return;const a=100,o=50,m=5,s=5,x=5,l=3,c=297,g=210,r=new O({orientation:"landscape",unit:"mm",format:[c,g]}),w=o+m,$=g-x*2,S=Math.max(1,Math.floor($/w));i.forEach((d,p)=>{const h=l*S,f=p%h;p>0&&f===0&&r.addPage([c,g],"landscape");const B=Math.floor(f/l),q=f%l,v=s+q*(a+m),b=x+B*w;r.setDrawColor(0,0,0),r.setLineWidth(.5),r.rect(v,b,a,o);const k=a-10,E=20,U=3,F=document.createElement("canvas");F.width=k*U,F.height=E*U,V(F,d,{width:1.5*U,height:E*U,displayValue:!1});const M=v+(a-k)/2;r.addImage(F,"PNG",M,b+3,k,E);let C=b+E+5;if(r.setFontSize(8),r.setFont(void 0,"bold"),r.text(`SERIAL: ${d}`,v+a/2,C,{align:"center"}),C+=4.5,r.setFontSize(9),r.setFont(void 0,"bold"),r.text(`${n||""}`,v+a/2,C,{align:"center"}),C+=3.5,e!=null&&e.repackUnitName&&(e!=null&&e.repackQty)){const H=parseFloat(Number(e.repackQty).toFixed(4)).toString();r.setFontSize(7),r.setFont(void 0,"bold"),r.text(`1 ${e.repackUnitName.toUpperCase()} = ${H} ${(e.unitName||"").toUpperCase()}`,v+a/2,C,{align:"center"})}});const N=i[0]||"serial";r.save(`${N}_labels_10x5cm.pdf`)},A=async i=>{var e,a;if((await u.fire({title:"Rollback serial?",text:"Semua serial untuk item ini akan dihapus (di GR ini).",icon:"warning",showCancelButton:!0,confirmButtonText:"Ya, rollback",cancelButtonText:"Batal",confirmButtonColor:"#d33"})).isConfirmed)try{const{data:o}=await I.delete(`/api/food-good-receive-items/${i.id}/serials`);await u.fire("Berhasil",(o==null?void 0:o.message)||"Rollback serial berhasil.","success"),await Q()}catch(o){const m=((a=(e=o==null?void 0:o.response)==null?void 0:e.data)==null?void 0:a.message)||"Gagal rollback serial.";await u.fire("Error",m,"error")}};return J(()=>{var i;return[P.show,(i=P.gr)==null?void 0:i.id]},([i])=>{i&&Q()},{immediate:!0}),(i,n)=>_.show?(R(),D("div",tt,[t("div",et,[n[8]||(n[8]=t("h2",{class:"text-xl font-bold mb-4 flex items-center gap-2"},[t("i",{class:"fa-solid fa-file-lines text-blue-500"}),Y(" Detail Good Receive ")],-1)),t("button",{onClick:n[0]||(n[0]=e=>i.$emit("close")),class:"absolute top-4 right-4 text-gray-400 hover:text-red-500"},n[1]||(n[1]=[t("i",{class:"fa-solid fa-xmark text-2xl"},null,-1)])),t("div",at,[t("div",null,[n[2]||(n[2]=t("div",{class:"text-sm text-gray-500"},"Tanggal",-1)),t("div",nt,y(_.gr.receive_date),1)]),t("div",null,[n[3]||(n[3]=t("div",{class:"text-sm text-gray-500"},"No. PO",-1)),t("div",it,y(_.gr.po_number),1)]),t("div",null,[n[4]||(n[4]=t("div",{class:"text-sm text-gray-500"},"Supplier",-1)),t("div",ot,y(_.gr.supplier_name),1)]),t("div",null,[n[5]||(n[5]=t("div",{class:"text-sm text-gray-500"},"Petugas",-1)),t("div",st,y(_.gr.received_by_name),1)])]),t("div",null,[n[7]||(n[7]=t("div",{class:"font-semibold mb-2"},"Daftar Item",-1)),t("table",rt,[n[6]||(n[6]=t("thead",{class:"bg-gray-100"},[t("tr",null,[t("th",{class:"border px-2 py-1"},"Nama Item"),t("th",{class:"border px-2 py-1"},"Qty Diterima"),t("th",{class:"border px-2 py-1"},"Unit"),t("th",{class:"border px-2 py-1"},"Harga"),t("th",{class:"border px-2 py-1"},"Total"),t("th",{class:"border px-2 py-1"},"Serial")])],-1)),t("tbody",null,[(R(!0),D(X,null,Z(_.gr.items||[],e=>(R(),D("tr",{key:e.id},[t("td",dt,y(e.item_name),1),t("td",lt,y(e.qty_received),1),t("td",ct,y(e.unit_name),1),t("td",pt,y(T(e.price)),1),t("td",ut,y(T(e.qty_received*e.price)),1),t("td",mt,[t("div",gt,[t("button",{type:"button",class:"px-2 py-1 text-xs rounded bg-blue-100 text-blue-700 hover:bg-blue-200",onClick:a=>G(e)}," Generate Serial ",8,bt),t("button",{type:"button",class:"px-2 py-1 text-xs rounded bg-gray-100 text-gray-700 hover:bg-gray-200",onClick:a=>z(e)}," Lihat Serial ("+y(L.value[e.id]||0)+") ",9,yt),t("button",{type:"button",class:"px-2 py-1 text-xs rounded bg-red-100 text-red-700 hover:bg-red-200",onClick:a=>A(e)}," Rollback Serial ",8,xt)])])]))),128))])])])])])):K("",!0)}};export{Bt as default};
