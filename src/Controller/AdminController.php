<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Uid\Uuid;

use App\Entity\User;
use App\Entity\Product;
use App\Entity\Order;
use App\Entity\Category;
use App\Entity\Review;
use App\Entity\Cart;

class AdminController extends AbstractController
{
    /**
     * @Route("/admin", name="admin")
     */
    public function index(): Response
    {
        $em = $this->getDoctrine()->getManager();

        // Find all products and their categories, users and orders
        $products = $em->getRepository(Product::class)->findAll();
        $users = $em->getRepository(User::class)->findAll();
        $categories = $em->getRepository(Category::class)->findAll();

        $ordersTemp = $em->getRepository(Order::class)->findAll();
        $orders = array();

        foreach($ordersTemp as $order){
          $orderCarts = $em->getRepository(Cart::class)->findByToken($order->getToken());
          $order->setCarts($orderCarts);
          $orders[] = $order;
        }

        return $this->render('admin/index.html.twig', [
            'products' => $products,
            'users' => $users,
            'orders' => $orders,
            'categories' => $categories

        ]);
    }

    /**
     * @Route("/admin/add-product", name="add-product")
     */
    public function addProduct(Request $request): Response{

        $params = $request->request;
        $files = $request->files;

        $err = array();
        $responseData;

        // Check if all POST parameters exist
        if(!$params->has('name')) $err[] = 'ERR NAME';
        if(!$params->has('price')) $err[] = 'ERR PRICE';
        if(!$files->has('picture')) $err[] = 'ERR PICTURE';
        if(!$params->has('description')) $err[] = 'ERR DESCRIPTION';
        if(!$params->has('category-id')) $err[] = 'ERR CATEGORY';

        // In case of error
        if(count($err) > 0){
          $responseData = array(
            "status" => "ERR",
            "errors" => $err
          );
        }else{

        // Create the new product
        $product = new Product();
        $product->setName($params->get('name'));
        $product->setPrice($params->get('price'));
        $product->setDescription($params->get('description'));

        $em = $this->getDoctrine()->getManager();
        $category = $em->getRepository(Category::class)->find($params->get("category-id"));

        $product->setCategory($category);

        // Set up the picture file
        $picture = $files->get("picture");

        $uniqueId = Uuid::v4();
        $dirPath = __DIR__."/../../public/img/products";

        $picture->move($dirPath, $uniqueId.".jpg");

        $product->setPicture($uniqueId);

        $em->persist($product);
        $em->flush();

        $responseData = array(
          "status" => "OK"
        );

        }

        $jsonResponse = new JsonResponse($responseData);
        return $jsonResponse;

    }

    /**
     * @Route("/admin/remove-product", name="remove-product")
     */
    public function removeProduct(Request $request): Response{

      $params = $request->request;

      $err = array();
      $responseData;

      // Check if POST parameter id exist
      if(!$params->has('id')) $err[] = 'ERR ID';

      // In case of error
      if(count($err) > 0){
        $responseData = array(
          "status" => "ERR",
          "errors" => $err
        );
      }else{

        $em = $this->getDoctrine()->getManager();
        $p = $em->getRepository(Product::class)->find($params->get('id'));
        $carts = $em->getRepository(Cart::class)->findByProduct($p);
        foreach($carts as $cart){
          $order = $em->getRepository(Order::class)->findOneByToken($cart->getToken());
          $em->remove($order);
          $em->remove($cart);
        }

        $reviews = $em->getRepository(Review::class)->findByProduct($p);
        foreach($reviews as $review){
          $em->remove($review);
        }

        if (!$p) throw $this->createNotFoundException("Pas de produit numéro $id dans la base !");

        // Delete the product
        $em->remove($p);
        $em->flush();
        $responseData = array(
          "status" => "OK"
        );

      }

      $jsonResponse = new JsonResponse($responseData);
      return $jsonResponse;

    }

    /**
     * @Route("/admin/remove-user", name="remove-user")
     */
    public function removeUser(Request $request): Response{
      $params = $request->request;

      $err = array();
      $responseData;

      // Check if POST parameter id exist
      if(!$params->has('id')) $err[] = 'ERR ID';

      // In case of error
      if(count($err) > 0){
        $responseData = array(
          "status" => "ERR",
          "errors" => $err
        );
      }else{
      $em = $this->getDoctrine()->getManager();
      $r = $em->getRepository(User::class)->find($params->get('id'));

      if (!$r) throw $this->createNotFoundException("Pas d'utilisateur numéro $id dans la base !");

      // Banish user by removing his password
      $r->setPassword('');
      $em->flush();
      $responseData = array(
        "status" => "OK"
      );

    }

    $jsonResponse = new JsonResponse($responseData);
    return $jsonResponse;
    }

    /**
     * @Route("/admin/validate-order", name="validate-order")
     */
    public function validateOrder(Request $request): Response{
      $params = $request->request;

      $err = array();
      $responseData;

      // Check if POST parameter id exist
      if(!$params->has('id')) $err[] = 'ERR ID';

      // In case of error
      if(count($err) > 0){
        $responseData = array(
          "status" => "ERR",
          "errors" => $err
        );
      }else{
        $em = $this->getDoctrine()->getManager();
        $q = $em->getRepository(Order::class)->find($params->get('id'));

        if (!$q) throw $this->createNotFoundException("Pas de commande numéro $id dans la base !");

        // Validate cart
        $q->setState('OK');
        $em->flush();
        $responseData = array(
          "status" => "OK"
        );

      }

      $jsonResponse = new JsonResponse($responseData);
      return $jsonResponse;

    }
}
