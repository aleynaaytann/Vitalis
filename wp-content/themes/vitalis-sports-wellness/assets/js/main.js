(()=>{
const b=document.querySelector('.menu-toggle'),n=document.querySelector('.main-nav');
if(b&&n)b.addEventListener('click',()=>{const o=b.getAttribute('aria-expanded')==='true';b.setAttribute('aria-expanded',String(!o));n.classList.toggle('is-open',!o);});
let activeDialog=null;
const openGallery=(link)=>{
 const gallery=link.closest('[data-gallery-set]');
 const links=Array.from((gallery||document).querySelectorAll('a[data-vitalis-lightbox]'));
 let index=Math.max(0,links.indexOf(link));
 const prior=document.activeElement;
 const box=document.createElement('div');box.className='vitalis-lightbox';box.setAttribute('role','dialog');box.setAttribute('aria-modal','true');box.setAttribute('aria-label','Fotoğraf galerisi');
 const stage=document.createElement('div');stage.className='vitalis-lightbox-stage';
 const image=document.createElement('img');image.className='vitalis-lightbox-image';
 const caption=document.createElement('div');caption.className='vitalis-lightbox-caption';
 const count=document.createElement('div');count.className='vitalis-lightbox-count';
 const button=(label,cls,text)=>{const el=document.createElement('button');el.type='button';el.className=cls;el.setAttribute('aria-label',label);el.textContent=text;return el;};
 const close=button('Galeriyi kapat','vitalis-lightbox-close','×');
 const prev=button('Önceki fotoğraf','vitalis-lightbox-prev','‹');
 const next=button('Sonraki fotoğraf','vitalis-lightbox-next','›');
 const draw=()=>{const item=links[index];image.src=item.href;image.alt=item.querySelector('img')?.alt||'';caption.textContent=image.alt;count.textContent=(index+1)+' / '+links.length;};
 const dismiss=()=>{if(!activeDialog)return;document.removeEventListener('keydown',keys);box.remove();document.body.style.overflow='';activeDialog=null;if(prior&&prior.focus)prior.focus();};
 const step=(delta)=>{index=(index+delta+links.length)%links.length;draw();};
 const keys=(event)=>{if(event.key==='Escape')dismiss();if(event.key==='ArrowLeft')step(-1);if(event.key==='ArrowRight')step(1);};
 close.addEventListener('click',dismiss);prev.addEventListener('click',()=>step(-1));next.addEventListener('click',()=>step(1));
 box.addEventListener('click',(event)=>{if(event.target===box)dismiss();});
 stage.append(image,caption);box.append(stage,count,close,prev,next);document.body.append(box);document.body.style.overflow='hidden';activeDialog=box;document.addEventListener('keydown',keys);draw();close.focus();
};
document.addEventListener('click',(event)=>{const link=event.target.closest('a[data-vitalis-lightbox]');if(!link)return;event.preventDefault();openGallery(link);});
})();

(()=>{
const resource=document.querySelector('#vitalis-resource');
const date=document.querySelector('#vitalis-booking-date');
const target=document.querySelector('#vitalis-booking-slots');
const daysTarget=document.querySelector('#vitalis-booking-days');
const chosen=document.querySelector('#vitalis-booking-time');
const form=document.querySelector('.vitalis-booking-form');
if(!resource||!date||!target||!chosen||!window.VitalisCore)return;
const monthSelect=document.querySelector('#vitalis-booking-month');
const yearSelect=document.querySelector('#vitalis-booking-year');
const previous=document.querySelector('#vitalis-booking-prev');
const next=document.querySelector('#vitalis-booking-next');
const selectedDay=document.querySelector('#vitalis-booking-selected-date');
const monthNames=VitalisCore.monthNames||['Ocak','Şubat','Mart','Nisan','Mayıs','Haziran','Temmuz','Ağustos','Eylül','Ekim','Kasım','Aralık'];
const weekdays=VitalisCore.weekdays||['Pzt','Sal','Çar','Per','Cum','Cmt','Paz'];
const today=new Date((VitalisCore.today||new Date().toISOString().slice(0,10))+'T12:00:00');
const minMonth=new Date(today.getFullYear(),today.getMonth(),1);
const maxParts=(VitalisCore.maxBookingMonth||'').split('-');
const maxMonth=maxParts.length===2?new Date(Number(maxParts[0]),Number(maxParts[1])-1,1):new Date(today.getFullYear(),today.getMonth()+12,1);
const monthIndex=value=>value.getFullYear()*12+value.getMonth();
const monthValue=value=>value.getFullYear()+'-'+String(value.getMonth()+1).padStart(2,'0');
let activeMonth=new Date(minMonth),slotRequest=0,monthRequest=0,selectedDate='';
const updatePeriod=()=>{
 monthSelect.replaceChildren();
 for(let month=0;month<12;month++){
  const value=new Date(activeMonth.getFullYear(),month,1);
  const option=document.createElement('option');option.value=String(month+1);option.textContent=monthNames[month];option.disabled=monthIndex(value)<monthIndex(minMonth)||monthIndex(value)>monthIndex(maxMonth);monthSelect.append(option);
 }
 monthSelect.value=String(activeMonth.getMonth()+1);
 yearSelect.replaceChildren();
 for(let year=minMonth.getFullYear();year<=maxMonth.getFullYear();year++){
  const option=document.createElement('option');option.value=String(year);option.textContent=String(year);yearSelect.append(option);
 }
 yearSelect.value=String(activeMonth.getFullYear());
 previous.disabled=monthIndex(activeMonth)<=monthIndex(minMonth);
 next.disabled=monthIndex(activeMonth)>=monthIndex(maxMonth);
};
const clearSelection=()=>{
 selectedDate='';date.value='';chosen.value='';slotRequest++;
 selectedDay.textContent='Takvimden bir gün seçin';
 target.textContent='Önce alan ve gün seçin.';
};
const load=async()=>{
 chosen.value='';
 if(!resource.value||!date.value){target.textContent='Önce alan ve tarih seçin.';return;}
 const current=++slotRequest;
 target.textContent=VitalisCore.loading||'Yükleniyor…';
 const params=new URLSearchParams({action:'vitalis_booking_slots',nonce:VitalisCore.nonce,resource:resource.value,date:date.value});
 try{
  const response=await fetch(VitalisCore.ajaxUrl,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'},body:params.toString(),credentials:'same-origin'});
  const result=await response.json();
  if(current!==slotRequest)return;
  target.replaceChildren();
  if(!result.success||!result.data.slots.length){target.textContent=result.data?.message||VitalisCore.empty||'Uygun saat bulunamadı.';return;}
  const message=document.createElement('span');message.className='slot-status';message.textContent=result.data.message||'';target.append(message);
  result.data.slots.forEach(slot=>{
   const button=document.createElement('button');button.type='button';button.className='slot-choice'+(slot.available?'':' is-busy');button.disabled=!slot.available;button.dataset.time=slot.value;
   const timeLabel=document.createElement('span');timeLabel.textContent=slot.label;
   const stateLabel=document.createElement('small');stateLabel.textContent=slot.available?'Boş':'Dolu';
   button.append(timeLabel,stateLabel);
   if(slot.available)button.addEventListener('click',()=>{target.querySelectorAll('.slot-choice').forEach(item=>item.classList.remove('is-selected'));button.classList.add('is-selected');chosen.value=slot.value;});
   target.append(button);
  });
 }catch(error){if(current===slotRequest)target.textContent='Saatler yüklenemedi. Sayfayı yenileyip tekrar deneyin.';}
};
const renderMonth=data=>{
 const days=data.days||[];
 updatePeriod();
 daysTarget.replaceChildren();
 weekdays.forEach(label=>{const item=document.createElement('span');item.className='booking-calendar-weekday';item.textContent=label;daysTarget.append(item);});
 const firstWeekday=days.length?Number(days[0].weekday):1;
 for(let blank=1;blank<firstWeekday;blank++){const empty=document.createElement('span');empty.className='booking-calendar-empty';empty.setAttribute('aria-hidden','true');daysTarget.append(empty);}
 days.forEach(day=>{
  const value=monthValue(activeMonth)+'-'+String(day.day).padStart(2,'0');
  const button=document.createElement('button');button.type='button';button.className='booking-day-choice is-'+day.status;button.dataset.date=value;
  button.disabled=day.status==='closed';
  button.setAttribute('aria-label',`${day.label}, ${day.status_label}, ${day.available} saat boş / ${day.total}`);
  if(selectedDate===value)button.classList.add('is-selected');
  const number=document.createElement('strong');number.textContent=String(day.day);
  const status=document.createElement('span');status.textContent=day.status_label;
  const count=document.createElement('small');count.textContent=day.status==='closed'?'—':`${day.available}/${day.total} saat`;
  button.append(number,status,count);
  if(!button.disabled)button.addEventListener('click',()=>{date.value=value;selectedDate=value;selectedDay.textContent=new Intl.DateTimeFormat('tr-TR',{weekday:'long',day:'numeric',month:'long',year:'numeric'}).format(new Date(value+'T12:00:00'));daysTarget.querySelectorAll('.booking-day-choice').forEach(item=>item.classList.toggle('is-selected',item===button));date.dispatchEvent(new Event('change',{bubbles:true}));});
  daysTarget.append(button);
 });
};
const loadMonth=async()=>{
 updatePeriod();
 const current=++monthRequest;
 if(!resource.value){daysTarget.removeAttribute('aria-busy');daysTarget.textContent='Önce kiralanabilir alanı seçin.';return;}
 daysTarget.setAttribute('aria-busy','true');daysTarget.textContent=VitalisCore.daysLoading||'Takvim yükleniyor…';
 const params=new URLSearchParams({action:'vitalis_booking_calendar_month',nonce:VitalisCore.nonce,resource:resource.value,month:monthValue(activeMonth)});
 try{
  const response=await fetch(VitalisCore.ajaxUrl,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'},body:params.toString(),credentials:'same-origin'});
  const result=await response.json();
  if(current!==monthRequest)return;
  if(!result.success||!result.data.days.length){daysTarget.textContent=result.data?.message||VitalisCore.daysEmpty||'Bu ay için uygunluk bulunamadı.';return;}
  renderMonth(result.data);
 }catch(error){if(current===monthRequest)daysTarget.textContent='Takvim yüklenemedi. Sayfayı yenileyip tekrar deneyin.';}
 finally{if(current===monthRequest)daysTarget.removeAttribute('aria-busy');}
};
monthSelect.addEventListener('change',()=>{activeMonth=new Date(activeMonth.getFullYear(),Number(monthSelect.value)-1,1);clearSelection();loadMonth();});
yearSelect.addEventListener('change',()=>{activeMonth=new Date(Number(yearSelect.value),activeMonth.getMonth(),1);if(monthIndex(activeMonth)<monthIndex(minMonth))activeMonth=new Date(minMonth);if(monthIndex(activeMonth)>monthIndex(maxMonth))activeMonth=new Date(maxMonth);clearSelection();updatePeriod();loadMonth();});
previous.addEventListener('click',()=>{if(monthIndex(activeMonth)<=monthIndex(minMonth))return;activeMonth=new Date(activeMonth.getFullYear(),activeMonth.getMonth()-1,1);clearSelection();loadMonth();});
next.addEventListener('click',()=>{if(monthIndex(activeMonth)>=monthIndex(maxMonth))return;activeMonth=new Date(activeMonth.getFullYear(),activeMonth.getMonth()+1,1);clearSelection();loadMonth();});
resource.addEventListener('change',()=>{clearSelection();loadMonth();});
date.addEventListener('change',load);
form?.addEventListener('submit',event=>{if(!date.value||!chosen.value){event.preventDefault();daysTarget.focus();daysTarget.setAttribute('tabindex','-1');daysTarget.focus();}});
updatePeriod();loadMonth();
})();

(()=>{
const controls=document.querySelector('.vitalis-accessibility-controls');
if(!controls)return;
const toggle=controls.querySelector('.vitalis-accessibility-toggle');
const root=document.documentElement;
const scaleClass='vitalis-font-scale-active';
const storageKey='vitalis-font-scale-v1';
const levels=[1,1.1,1.2,1.3,1.4,1.5];
const status=controls.querySelector('.vitalis-font-status');
const decrease=controls.querySelector('[data-font-action="decrease"]');
const reset=controls.querySelector('[data-font-action="reset"]');
const increase=controls.querySelector('[data-font-action="increase"]');
const hoverPointer=window.matchMedia('(hover: hover) and (pointer: fine)');
const isFocused=()=>controls.contains(document.activeElement);
const syncToggleState=()=>{
 const hovered=hoverPointer.matches&&controls.matches(':hover');
 const focused=isFocused();
 const pinned=controls.classList.contains('is-open');
 const open=pinned||hovered||focused;
 toggle.setAttribute('aria-expanded',String(open));
 toggle.setAttribute('aria-label',pinned?'Yazı boyutu ayarlarını kapat':(hovered||focused)?'Yazı boyutu ayarlarını açık tut':'Yazı boyutu ayarlarını aç');
};
let scale=1;
try{
 const saved=Number(window.localStorage.getItem(storageKey));
 if(Number.isFinite(saved)&&saved>=1&&saved<=1.5)scale=levels.reduce((best,value)=>Math.abs(value-saved)<Math.abs(best-saved)?value:best,1);
}catch(error){}

const isControl=(element)=>element.closest('.vitalis-accessibility-controls');
const captureElement=(element,force)=>{
 if(!element||element.nodeType!==1||isControl(element))return;
 if(!force&&element.hasAttribute('data-vitalis-font-base'))return;
 const size=parseFloat(window.getComputedStyle(element).fontSize);
 if(Number.isFinite(size)&&size>0)element.setAttribute('data-vitalis-font-base',String(size));
};
const captureTree=(element,force)=>{
 if(!element||element.nodeType!==1||isControl(element))return;
 captureElement(element,force);
 element.querySelectorAll('*').forEach(child=>captureElement(child,force));
};
const applyScale=()=>{
 root.style.setProperty('--vitalis-font-scale',String(scale));
 document.body.querySelectorAll('[data-vitalis-font-base]').forEach(element=>{
  if(isControl(element))return;
  const base=parseFloat(element.getAttribute('data-vitalis-font-base'));
  if(Number.isFinite(base))element.style.setProperty('--vitalis-scaled-font-size',(base*scale).toFixed(3)+'px');
 });
 root.classList.toggle(scaleClass,scale>1);
 status.textContent='%' + Math.round(scale*100);
 decrease.disabled=scale<=levels[0];
 increase.disabled=scale>=levels[levels.length-1];
};
const saveScale=()=>{try{window.localStorage.setItem(storageKey,String(scale));}catch(error){}}

captureTree(document.body,false);
applyScale();
controls.addEventListener('click',event=>{
 const button=event.target.closest('[data-font-action]');
 if(!button)return;
 const action=button.getAttribute('data-font-action');
 const index=levels.indexOf(scale);
 if(action==='increase')scale=levels[Math.min(levels.length-1,index+1)];
 else if(action==='decrease')scale=levels[Math.max(0,index-1)];
 else scale=1;
 applyScale();
 saveScale();
});
toggle.addEventListener('click',()=>{
 const open=!controls.classList.contains('is-open');
 controls.classList.toggle('is-open',open);
 if(!open)toggle.blur();
 syncToggleState();
});
controls.addEventListener('mouseenter',syncToggleState);
controls.addEventListener('mouseleave',syncToggleState);
controls.addEventListener('focusin',syncToggleState);
controls.addEventListener('focusout',()=>window.requestAnimationFrame(syncToggleState));
document.addEventListener('click',event=>{
 if(controls.contains(event.target)||!controls.classList.contains('is-open'))return;
 controls.classList.remove('is-open');
 if(isFocused())document.activeElement.blur();
 syncToggleState();
});
controls.addEventListener('keydown',event=>{
 if(event.key!=='Escape')return;
 controls.classList.remove('is-open');
 if(isFocused())document.activeElement.blur();
 syncToggleState();
});
syncToggleState();

const observer=new MutationObserver(records=>{
 const additions=[];
 records.forEach(record=>record.addedNodes.forEach(node=>{if(node.nodeType===1&&!isControl(node))additions.push(node);}));
 if(!additions.length)return;
 const wasScaled=root.classList.contains(scaleClass);
 if(wasScaled)root.classList.remove(scaleClass);
 additions.forEach(node=>captureTree(node,false));
 if(wasScaled)applyScale();
});
observer.observe(document.body,{childList:true,subtree:true});

let resizeTimer;
window.addEventListener('resize',()=>{
 window.clearTimeout(resizeTimer);
 resizeTimer=window.setTimeout(()=>{
  const wasScaled=root.classList.contains(scaleClass);
  if(wasScaled)root.classList.remove(scaleClass);
  captureTree(document.body,true);
  applyScale();
 },120);
});
})();
