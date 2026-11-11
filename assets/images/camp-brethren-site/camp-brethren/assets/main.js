const menu=document.querySelector('.menu');
const nav=document.querySelector('#site-nav');

if(menu&&nav){
  const closeMenu=()=>{nav.classList.remove('open');menu.setAttribute('aria-expanded','false')};
  menu.addEventListener('click',()=>{
    const isOpen=menu.getAttribute('aria-expanded')!=='true';
    nav.classList.toggle('open',isOpen);
    menu.setAttribute('aria-expanded',String(isOpen));
  });
  nav.addEventListener('click',event=>{
    if(event.target instanceof HTMLAnchorElement)closeMenu();
  });
  document.addEventListener('keydown',event=>{
    if(event.key==='Escape')closeMenu();
  });
}

document.querySelectorAll('form[data-inquiry-form]').forEach(form=>form.addEventListener('submit',async event=>{
  event.preventDefault();
  const status=form.parentElement.querySelector('.ok');
  const submit=form.querySelector('button[type="submit"]');
  if(!status||!submit)return;

  const values=new FormData(form);
  const inquiry={
    type:form.dataset.inquiryForm,
    name:values.get('name'),
    phone:values.get('phone'),
    email:values.get('email')||'',
    ...(form.dataset.inquiryForm==='admission'
      ?{class:values.get('class')}
      :{message:values.get('msg')})
  };

  status.style.display='none';
  status.textContent='';
  submit.disabled=true;

  try{
    const response=await fetch('/api/inquiries',{
      method:'POST',
      headers:{'Content-Type':'application/json','Accept':'application/json'},
      body:JSON.stringify(inquiry)
    });
    const result=await response.json();
    if(!response.ok){
      const fieldErrors=Array.isArray(result.fields)
        ?` ${result.fields.map(field=>field.message).join(' ')}`
        :'';
      throw new Error(`${result.error||'Your enquiry could not be sent.'}${fieldErrors}`);
    }

    form.reset();
    status.textContent=`Thank you. Your enquiry has been received. Reference: ${result.reference}`;
  }catch(error){
    console.error('Enquiry submission failed:',error);
    status.textContent=error instanceof TypeError
      ?'We could not connect to the enquiry service. Please try again later.'
      :error.message;
  }finally{
    status.style.display='block';
    status.focus();
    submit.disabled=false;
  }
}));

document.querySelectorAll('[data-year]').forEach(element=>{
  element.textContent=String(new Date().getFullYear());
});
