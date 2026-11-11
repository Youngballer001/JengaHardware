const loginPanel=document.querySelector('#login-panel');
const dashboardPanel=document.querySelector('#dashboard-panel');
const loginForm=document.querySelector('#login-form');
const loginStatus=document.querySelector('#login-status');
const dashboardStatus=document.querySelector('#dashboard-status');
const inquiriesBody=document.querySelector('#inquiries-body');
const pageSize=20;
let offset=0;
let total=0;

function showStatus(element,message,isError=false){
  element.textContent=message;
  element.classList.toggle('error',isError);
}

function showDashboard(authenticated){
  loginPanel.hidden=authenticated;
  dashboardPanel.hidden=!authenticated;
  document.querySelector('#logout-button').hidden=!authenticated;
}

async function requestJson(url,options){
  const response=await fetch(url,{
    credentials:'same-origin',
    headers:{'Accept':'application/json',...(options?.headers||{})},
    ...options
  });
  if(response.status===204)return null;
  const body=await response.json();
  if(!response.ok)throw new Error(body.error||'The request could not be completed.');
  return body;
}

function addCell(row,value){
  const cell=document.createElement('td');
  cell.textContent=value||'—';
  row.append(cell);
}

async function loadInquiries(){
  showStatus(dashboardStatus,'Loading enquiries…');
  try{
    const result=await requestJson(`/api/admin/inquiries?limit=${pageSize}&offset=${offset}`);
    total=result.total;
    inquiriesBody.replaceChildren();
    for(const inquiry of result.inquiries){
      const row=document.createElement('tr');
      const received=new Date(inquiry.created_at);
      addCell(row,Number.isNaN(received.getTime())?'—':received.toLocaleString());
      addCell(row,inquiry.type==='admission'?'Admission':'Contact');
      addCell(row,inquiry.name);
      addCell(row,inquiry.phone);
      addCell(row,inquiry.email);
      addCell(row,inquiry.class);
      addCell(row,inquiry.message);
      addCell(row,inquiry.notification_status);
      addCell(row,inquiry.id);
      inquiriesBody.append(row);
    }

    const first=total===0?0:offset+1;
    const last=Math.min(offset+result.inquiries.length,total);
    document.querySelector('#page-summary').textContent=`${first}–${last} of ${total} enquiries`;
    document.querySelector('#previous-button').disabled=offset===0;
    document.querySelector('#next-button').disabled=offset+pageSize>=total;
    showStatus(dashboardStatus,result.inquiries.length===0?'No enquiries have been received.':'');
  }catch(error){
    if(error.status===401||error.message.includes('sign in')){
      showDashboard(false);
      showStatus(loginStatus,'Your session expired. Please sign in again.',true);
      return;
    }
    showStatus(dashboardStatus,error.message,true);
  }
}

loginForm.addEventListener('submit',async event=>{
  event.preventDefault();
  const button=document.querySelector('#login-button');
  button.disabled=true;
  showStatus(loginStatus,'Signing in…');
  try{
    const formData=new FormData(loginForm);
    const session=await requestJson('/api/admin/session',{
      method:'POST',
      headers:{'Content-Type':'application/json'},
      body:JSON.stringify({
        username:formData.get('username'),
        password:formData.get('password')
      })
    });
    loginForm.reset();
    showStatus(loginStatus,'');
    showDashboard(true);
    document.querySelector('#email-status').hidden=session.emailNotifications;
    await loadInquiries();
  }catch(error){
    showStatus(loginStatus,error.message,true);
  }finally{
    button.disabled=false;
  }
});

document.querySelector('#logout-button').addEventListener('click',async()=>{
  try{
    await requestJson('/api/admin/logout',{method:'POST'});
  }catch(error){
    showStatus(dashboardStatus,error.message,true);
  }
  showDashboard(false);
});

document.querySelector('#refresh-button').addEventListener('click',loadInquiries);
document.querySelector('#previous-button').addEventListener('click',()=>{
  offset=Math.max(0,offset-pageSize);
  loadInquiries();
});
document.querySelector('#next-button').addEventListener('click',()=>{
  offset+=pageSize;
  loadInquiries();
});

requestJson('/api/admin/session')
  .then(session=>{
    document.querySelector('#setup-note').hidden=session.configured;
    if(session.authenticated){
      showDashboard(true);
      document.querySelector('#email-status').hidden=session.emailNotifications;
      return loadInquiries();
    }
  })
  .catch(error=>showStatus(loginStatus,error.message,true));
