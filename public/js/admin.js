$('body').on('click', '.admin-view-toggle', function(){

  $('.admin-view-toggle.active').removeClass('active');
  $(this).addClass('active');

  let targetContainer = $(this).attr('data-target');

  $('.admin-container').each(function(){
    $(this).addClass('d-none');
  });
  $('.'+targetContainer).removeClass('d-none');

});


//  Add product
$('#form-add-product').submit(function(e){
  e.preventDefault();
  var formData = new FormData(this);

  $.ajax({
    url: $('#form-add-product').attr('action'),
    type: 'POST',
    data: formData,
    success: function(data){
      if(data.status == "OK"){
        $('#add-product-modal').modal('hide')
        $('#form-add-product').trigger("reset");
        alert('Produit ajouté avec succès !')
      }
    },
    cache:false,
    contentType:false,
    processData: false
  })
})


//   Remove products
$('body').on('click','.btn-remove-product',function(){
  let id = $(this).attr("data-product-id");

  $.post(
    "/admin/remove-product",
    {
      "id" : id
    },
    function(response){
      if(response.status == "OK"){
        $('.product-container[data-product-id="'+id+'"]').remove();
      };
    }
  )
})

//   Validate order
$('body').on('click','.btn-validate-order',function(){
  let id = $(this).attr("data-order-id");
  var btn=$(this);

  $.post(
    "/admin/validate-order",
    {
      "id" : id
    },
    function(response){
      if(response.status == "OK"){
        btn.replaceWith("[Validée]");
      }
    }
  )
})

//   Banish user
$('body').on('click','.btn-remove-user',function(){
  let id = $(this).attr("data-user-id");


  $.post(
    "/admin/remove-user",
    {
      "id" : id
    },
    function(response){
      if(response.status == "OK"){
        $('.ban-user[data-user-id="'+id+'"]').remove();
      }

    }
  )
})
