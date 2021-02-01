//  Add to cart
$('body').on('click','.btn-add-to-cart',function(){
  let url="/add-to-cart";
  let productId=$(this).attr("data-product-id");
  let quantity=$(".input-quantity").val();
  $.post(
    url,
    {
      "product-id":productId,
      "quantity":quantity
    },
    function(response){
      initCart()
    }
  )
})

//  Get cart
var initCart = function(){
  $.get(
    "/my-cart",
    {},
    function(response){
      if(response.status == "OK"){
        if(response.carts.length > 0){
          $('.total-cart').removeClass('d-none');
          $(".empty-cart").addClass('d-none');
        }else{
          $(".empty-cart").removeClass('d-none');
          $('.total-cart').addClass('d-none');
        }
        let total = 0;
        $('.counter').text(response.carts.length);

        $('#cart-products-container').empty();

        $.each(response.carts, function(index, cart){
          let item = generateCartItem(cart);
          $('#cart-products-container').append(item);
            total += parseInt(cart.quantity)*parseFloat(cart.product_price);
        });
        $('.total-cart-price').text(total.toFixed(2));
      }
    }
  )
}


$(document).ready(function(){
  initCart()
});


var generateCartItem = function(cart){
  return `
  <li class="list-group-item p-0" data-product-id="`+cart.product_id+`">
    <div class="d-inline-block cart-product-image"
    style="background-image: url('/img/products/`+cart.product_picture+`.jpg');">

    </div>
    <div class="d-inline-block cart-product-informations">
      <div class="lead font-weight-bold">`+cart.product_name+`</div>
      <div>Prix : `+cart.product_price+` €</div>
      <div>Quantité : `+cart.quantity+`</div>
      <div class="text-center"><button class="btn btn-remove-product-cart btn-danger btn-sm my-2" data-cart-id="`+cart.cart_id+`" data-product-id="`+cart.product_id+`">Supprimer <i class="fas fa-times ml-1"></i></button></div>
    </div>
  </li>
  `
}

//  Validate cart
$('body').on('click','.btn-validate-cart',function(){
  let url="/validate-cart";
  $.get(
    url,
    {},
    function(response){
      // remove cart items
      if(response.status == "OK"){
        $('.total-cart').addClass('d-none');
        $(".empty-cart").removeClass('d-none');
        $('.counter').text("0");
        $('#cart-products-container').empty();
      }else{
        alert(response.err);
      }
    }
  );
});

//  Remove product from cart
$('body').on('click','.btn-remove-product-cart',function(){
  let id = $(this).attr('data-cart-id');
  $.post(
    "/remove-product-cart",
    {
      "id" : id
    },
    function(response){
      initCart()
    }
  )
})
