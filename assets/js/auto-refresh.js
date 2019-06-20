var rzp_refresh_time = 15;
var rzp_actual_refresh_time = rzp_refresh_time + 1;

setTimeout(function(){
  window.location.href = 'https://www.tutorialspoint.com/javascript/';
}, rzp_refresh_time * 1000);

setInterval(function(){ 
  if(rzp_actual_refresh_time >0) {
    rzp_actual_refresh_time--
    //console.log(rzp_actual_refresh_time)
    document.getElementById('rzp_refresh_timer').innerText = rzp_actual_refresh_time
  } else {
    clearInterval(rzp_actual_refresh_time)
  }
}, 1000);