<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

use  Symfony\Component\HttpFoundation\JsonResponse;
use  Symfony\Component\HttpFoundation\Cookie;
use  Symfony\Component\Uid\Uuid;

use App\Entity\Category;
use App\Entity\Product;
use App\Entity\Cart;
use App\Entity\Order;
use App\Entity\Review;

class MainController extends AbstractController
{
    /**
     * @Route("/", name="app_homepage")
     */
    public function index(): Response
    {
      $categories = $this->getDoctrine()
        ->getRepository(Category::class)
        ->findAll();

        return $this->render('main/index.html.twig', [
            'categories' => $categories
        ]);
    }

    /**
     * @Route("/search", name="search")
     */
    public function search(Request $request): Response
    {
      $params = $request->query;

      // Check if the GET parameter "name" exist
      if($params->has('name') && (strlen($params->get('name'))>0)){
        $productName = $params->get('name');
      }else{
        return $this->redirectToRoute('app_homepage');
      }

      $products = $this->getDoctrine()
        ->getRepository(Product::class)
        ->searchByName($productName);

        return $this->render('main/search.html.twig', [
            'products' => $products
        ]);
    }


    /**
     * @Route("/list/{id}", name="list")
     */
    public function list($id): Response
    {
      $products = $this->getDoctrine()
        ->getRepository(Product::class)
        ->findByCategory($id);

      $category = $this->getDoctrine()
        ->getRepository(Category::class)
        ->find($id);

        return $this->render('main/list.html.twig', [
            'products' => $products,
            'category' => $category
        ]);
    }


    /**
     * @Route("/product/{id}", name="product")
     */
    public function product(Request $request,$id): Response
    {
      $params = $request->request;
      $rate = null;
      $comment = null;
      if($params->has('rate')) $rate = $params->get('rate');
      if($params->has('comment')) $comment = $params->get('comment');

      $product = $this->getDoctrine()
        ->getRepository(Product::class)
        ->find($id);

      if(($rate!=null) && ($comment!=null) && ($product!=null) ){
          $review = new Review();
          $review->setComment($comment);
          $review->setRate($rate);
          $review->setProduct($product);
          $review->setUser($this->getUser()->getName());

          $em = $this->getDoctrine()->getManager();
          $em->persist($review);
          $em->flush();

      }

      $product = $this->getDoctrine()
        ->getRepository(Product::class)
        ->find($id);

        return $this->render('main/product.html.twig', [
            'product' => $product,

        ]);
    }


    /**
     * @Route("/my-cart", name="my-cart")
     */
    public function myCart(Request $request): Response
    {
      $token = $request->cookies->get('token');
      $carts = array();
	  $jsonCarts = array();

      if( $token != null) {
        $em = $this->getDoctrine()->getManager();
        $carts = $em->getRepository(Cart::class)->findByToken($token);

        foreach($carts as $cart){
          $jsonContent = array(
            "cart_id" => $cart->getId(),
            "product_id" => $cart->getProduct()->getId(),
            "product_price" => $cart->getProduct()->getPrice(),
            "product_picture" => $cart->getProduct()->getPicture(),
            "product_name"  => $cart->getProduct()->getName(),
            "quantity" => $cart->getQuantity()
          );
          $jsonCarts[] = $jsonContent;
        }

      }

      $responseData = array(
        "status" => "OK",
        "carts" => $jsonCarts
      );

      $jsonResponse = new JsonResponse($responseData);
      return $jsonResponse;

    }




    /**
     * @Route("/user/my-orders", name="my-orders")
     */
    public function myOrders(): Response
    {
        $em = $this->getDoctrine()->getManager();

        $ordersTemp = $em->getRepository(Order::class)->findByUser($this->getUser());
        $orders = array();

        foreach($ordersTemp as $order){
          $orderCarts = $em->getRepository(Cart::class)->findByToken($order->getToken());
          $order->setCarts($orderCarts);
          $orders[] = $order;
        }

        return $this->render('main/my_orders.html.twig', [
            'orders' => $orders
        ]);
    }

    /**
     * @Route("/add-to-cart", name="add-to-cart")
     */
    public function addToCart(Request $request): Response{

        $params = $request->request;
        $err = array();
        $responseData;

        if(!$params->has('product-id')) $err[] = 'NO PRODUCT';
        if(!$params->has('quantity')) $err[] = 'NO QUANTITY';

        $isNullToken = false;

        if(count($err) > 0){
          $responseData = array(
            "status" => "ERR",
            "errors" => $err
          );
        }else{

          $token = $request->cookies->get('token');

          if($token == null) {
            $token = Uuid::v4();
            $isNullToken = true;
          }

          $em = $this->getDoctrine()->getManager();
          $product = $em->getRepository(Product::class)->find($params->get("product-id"));

          if(!$product){
            $err[] = "NO SUCH PRODUCT";
          }
          if(!(intval($params->get("quantity"))>0)){
            $err[] = "ERR QUANTITY";
          }

          if(count($err) > 0){
            $responseData = array(
              "status" => "ERR",
              "errors" => $err
            );
          }else{

            $carts = $em->getRepository(Cart::class)->findByToken($token);
            $alreadyExists = false;
            foreach($carts as $cart){
              if($cart->getProduct()->getId() == $product->getId()){
                $cart->setQuantity($cart->getQuantity() + intval($params->get("quantity")));
                $alreadyExists = true;
                $em->persist($cart);
                $em->flush();
              }
            }

            if($alreadyExists == false){
              $cart = new Cart();
              $cart->setProduct($product);
              $cart->setQuantity($params->get('quantity'));
              $cart->setToken($token);

              $em->persist($cart);
              $em->flush();
            }

            $responseData = array(
              "status" => "OK"
            );
          }

        }

        $jsonResponse = new JsonResponse($responseData);
        if($isNullToken){
          $jsonResponse->headers->setCookie(Cookie::create('token')
            ->withValue($token)
            ->withExpires(time() + (60*60*24*7))
          );
        }
        return $jsonResponse;

    }

    /**
     * @Route("/validate-cart", name="validate-cart")
     */
    public function validateCart(Request $request): Response{

        $err = array();
        $responseData;

        $token = $request->cookies->get('token');
        if($token == null) {
          $err[] = "ERR TOKEN";
        }else{
          $em = $this->getDoctrine()->getManager();
          $carts = $em->getRepository(Cart::class)->findByToken($token);
          if(!(count($carts)>0)){
            $err[] = "ERR CART";
          }
        }

        if($this->getUser() == null){
          $err[] = "ERR USER";
        }

        if(count($err) > 0){
          $responseData = array(
            "status" => "ERR",
            "err" => $err
          );
        }else{
          $order = new Order();
          $order->setToken($token);
          $order->setUser($this->getUser());
          $order->setDate(new \DateTime('@'.strtotime('now')));

          $em = $this->getDoctrine()->getManager();
          $em->persist($order);
          $em->flush();

          $responseData = array(
            "status" => "OK"
          );

        }

        $jsonResponse = new JsonResponse($responseData);

        if(count($err) == 0){
          $jsonResponse->headers->clearCookie("token");
        }

        return $jsonResponse;

    }

    /**
     * @Route("/remove-product-cart", name="remove-product-cart")
     */
    public function removeProductCart(Request $request): Response{

      $params = $request->request;

      $err = array();
      $responseData;

      if(!$params->has('id')) $err[] = 'ERR ID';
      if(count($err) > 0){
        $responseData = array(
          "status" => "ERR",
          "errors" => $err
        );
      }else{

        $em = $this->getDoctrine()->getManager();
        $p = $em->getRepository(Cart::class)->find($params->get('id'));

        $em->remove($p);
        $em->flush();
        $responseData = array(
          "status" => "OK"
        );

      }

      $jsonResponse = new JsonResponse($responseData);
      return $jsonResponse;

    }



}
