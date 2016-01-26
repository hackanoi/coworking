<?php
namespace AppBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use AppBundle\Utils;
use Symfony\Component\HttpFoundation\Response;

/**
 * @Route("/room")
 */
class RoomController extends Controller
{

  /**
   * @Route("/", name = "room_list")
   */
  public function indexAction(){
    // Get form builder.
    $formbuilder = $this->get('app.formbuilder');
    $search_form = $this->getSearchForm();
    $form = $formbuilder->GenerateManualSearchControls($search_form);
    return $this->render('room/index.html.twig', [
      // 'groups' => $groups,
      'form' => $form,
      'script' => $formbuilder->mscript,
    ]);
  }

  /**
   * @Route("/add", name = "room_add")
   */
  public function addFormAction(){
    $formbuilder = $this->get('app.formbuilder');
    $tmp = $formbuilder->GenerateLayout('room');
    return $this->render('room/form.html.twig', [
      'base_dir' => realpath($this->container->getParameter('kernel.root_dir').'/..'),
      'form' => $tmp,
      'script' => $formbuilder->mscript
    ]);
  }

  /**
   * @Route("/{id}/edit", name = "room_edit", requirements={"id" = "\d+"})
   */
  public function editFormAction($id){
    $formbuilder = $this->get('app.formbuilder');
    $request = Request::createFromGlobals();
    $em = $this->getDoctrine()->getEntityManager();
    $connection = $em->getConnection();
    $statement = $connection->prepare("SELECT * FROM `room` WHERE id =:id");
    $statement->bindParam(':id', $id);
    $statement->execute();
    $row = $statement->fetchAll();
    $tmp = $formbuilder->LoadDatarowToConfig($row[0], 'room');
    return $this->render('room/form.html.twig', [
      'base_dir' => realpath($this->container->getParameter('kernel.root_dir').'/..'),
      'form' => $tmp,
      'script' => $formbuilder->mscript
    ]);
  }

  /**
   * @Route("/update", name = "room_update")
   *
   * Controll add/edit/delete room into database.
   */
  public function updateRoomAction(Request $request){
    $em = $this->getDoctrine()->getEntityManager();
    $connection = $em->getConnection();
    $action = $request->query->get('action');
    switch ($action) {
      case 'add':
        $room = array(
          'name' => $_POST['name'],
          'code' => $_POST['code'],
          'note' => $_POST['note'],
          'type' => $_POST['type'],
          'status' => $_POST['status'],
        );
        $data['v'] = $connection->insert('room', $room);
        $data['m'] = 'Thêm thành công phòng';
        break;

      case 'edit':
        $room = array(
          'name' => $_POST['name'],
          'code' => $_POST['code'],
          'note' => $_POST['note'],
          'type' => $_POST['type'],
          'status' => $_POST['status'],
        );
        $data['v'] = $connection->update('room', $room, array('id' => $_POST['id']));
        $data['m'] = 'Sửa thành công thông tin phòng';
        $data['rdr'] = $this->generateUrl('room_list');
        break;

      case 'delete':
        #...
        break;
    }
    $response = new Response(
      json_encode($data),
      Response::HTTP_OK,
      array('content-type' => 'application/json')
    );
    return $response;
  }

  /**
   * @Route("/search", name = "room_search")
   */
  public function searchAction(){
    // Get connection to database
    $em = $this->getDoctrine()->getEntityManager();
    $connection = $em->getConnection();
    // Get query search
    $formbuilder = $this->get('app.formbuilder');
    $grp = $this->getSearchForm();
    $searchData = $formbuilder->GetSearchData($_POST, $grp);
    $filters = $this->getWhereUserSearchCondition($searchData);
    $statement = $connection->prepare("SELECT * FROM `room` WHERE 1 $filters");
    $statement->execute();
    $all_rows = $statement->fetchAll();
    $ret = array();
    $idx = 0;
    if (empty($all_rows)){
      $data['empty'] = 'Không tìm thấy bản ghi nào';
    } else {
      foreach ($all_rows as $row){
        $type = ('meeting_room' == $row['type']? 'Phòng họp' : 'Sự kiện');
        $status = (1 == $row['status'] ? 'Đang hoạt động' : 'Ngừng hoạt động');
        $tmp = array(
          'id' => $row['id'],
          'idx' => ++$idx,
          'name' => $row['name'],
          'code' => $row['code'],
          'note' => $row['note'],
          'type' => $type,
          'status' => $status,
        );
        $ret[] = $tmp;
      }
      $data = $ret;
    }
    $response = new Response(
      json_encode($data),
      Response::HTTP_OK,
      array('content-type' => 'application/json')
    );
    return $response;
  }

  /**
   * Build search form.
   */
  private function getSearchForm(){
    $retval = array();
    // Room name.
    $row = array();
    $row['id'] = 'name';
    $row['label'] = 'Tên phòng';
    $row['type'] = 'text';
    $row['colname'] = 'name';
    $row['pos'] = array('row' => 1, 'col' => 1);
    $retval[] = $row;

    // Room code.
    $row = array();
    $row['id'] = 'code';
    $row['label'] = 'Mã';
    $row['type'] = 'text';
    $row['colname'] = 'code';
    $row['pos'] = array('row' => 1, 'col' => 2);
    $retval[] = $row;

    // Type
    $row = array();
    $row['id'] = 'type';
    $row['label'] = 'Loại';
    $row['colname'] = 'type';
    $row['type'] = 'check';
    $arr = array(
      'sameline' => 1,
      'label' => array('Phòng họp', 'Phòng sự kiện'),
      'value' => array("'meeting_room'", "'event_room'"),
    );
    $row['ds'] = json_encode($arr);
    $row['pos'] = array('row' => 2, 'col' => 1);
    $retval[] = $row;

    // Status
    $row = array();
    $row['id'] = 'status';
    $row['label'] = 'Trạng thái';
    $row['colname'] = 'status';
    $row['type'] = 'check';
    $arr = array(
      'sameline' => 1,
      'value' => array(1, 0),
      'label' => array('Đang hoạt động', 'Ngừng hoạt động')
    );
    $row['ds'] = json_encode($arr);
    $row['pos'] = array('row' => 2, 'col' => 2);
    $retval[] = $row;
    return $retval;
  }

  /**
   * Build condition where for user search
   */
  private function getWhereUserSearchCondition($searchData) {
    $formbuilder = $this->get('app.formbuilder');
    $where = '';
    foreach ($searchData as $data) {
      if ($data['colname'] != 'x') {
        $where .= $formbuilder->buildSingleCondition($data);
      } else {
      // custom condition
      }
    }
    return $where;
  }
}
