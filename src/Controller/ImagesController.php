<?php

namespace App\Controller;

use Burzum\Imagine\Lib\ImageProcessor;
use Cake\Core\Exception\Exception;

/**
 * Images Controller
 *
 * @property \App\Model\Table\ImagesTable $Images
 *
 * @method \App\Model\Entity\Image[] paginate($object = null, array $settings = [])
 */
class ImagesController extends AppController
{

    /**
     * Index method
     *
     * @return \Cake\Http\Response|void
     */
    public function index()
    {
        $searchText = $this->request->getData('q');
        if (!empty($searchText)) {
            try {
                $searchArray = $this->returnSearchArray($searchText);
                $searchQuery = $this->returnSearchQuery($searchArray);
                $query = $this->Images->find('all', [
                    'conditions' => $searchQuery
                ]);
            } catch (\Exception $ex) {
                $this->Flash->error($ex->getMessage());
                $query = $this->Images->find();
            }

        } else {
            $query = $this->Images->find();
        }

        $images = $this->paginate($query);

        $this->set(compact('images'));
        $this->set('_serialize', ['images']);
    }

    /**
     * Return search array based on regex
     * @param $str
     * @return array|false|string[]
     */
    private function returnSearchArray($str)
    {

        $subMatches = array();
        $regx = '/(<|>|=)|(CONTAINS)|(AND|OR)|\(.*\)/';

        // find all matches from the regex
        preg_match_all($regx, $str, $matches);

        if (count($matches[0]) > 0) {
            // check if the match starts with ( ), strip them, and return new query from substring
            foreach ($matches[0] as $m) {
                // if it starts with (, recursion the string with removed ()
                if (substr($m = trim($m), 0, 1) === '(') {
                    $subMatches[] = $this->returnSearchArray(substr($m, 1, -1));
                }
            }
        }

        // split all values by the regex
        $array = preg_split($regx, $str, 0, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

        // fill in the submatches
        if (count($subMatches) > 0) {
            for ($i = 0; $i < count($array); $i++) {
                $j = 0;
                if ($array[$i] === " ") {
                    $array[$i] = $subMatches[$j++];
                }
            }
        }
        return $array;
    }

    /**
     * Returns search query from splitted values
     * @param $searchArray Array from regex splitted value
     * @return array
     */
    private function returnSearchQuery($searchArray)
    {
        if (count($searchArray) == 1) {
            return [
                'filename LIKE' => '%' . $searchArray[0] . '%'
            ];
        }

        // format the array first
        $keyOpValParsed = $this->keyOperatorValueParse($searchArray);

        // build the query
        $queryTextParse = $this->queryTextValueParser($keyOpValParsed);

        // rearrange if necessary
        $queryRearanged = $this->queryRearange($queryTextParse);

        return $queryRearanged;
    }

    /**
     * Parses the search array into Key - Operator - Value - NextOp - SubQuery
     * @param $array
     * @return array
     */
    private function keyOperatorValueParse($array)
    {
        if (!is_array($array)) {
            return $array; // its probably just a simple search on filename
        }

        if (count($array) == 5) {
            // it has this form: filename CONTAINS value AND (...)
            return [
                trim($array[0]),
                trim($array[1]),
                trim($array[2]),
                trim($array[3]),
                $this->keyOperatorValueParse($array[4])
            ];
        } else if (count($array) % 4 == 3) {
            // it has this form: imgh < value AND imgh > value OR ... (total 3, 7, 11, ... keys)
            $temp = array();
            $ret = array();
            for ($i = 0; $i < count($array); $i++) {
                $temp[] = trim($array[$i]);
                if ($i % 4 == 3) {
                    $ret[] = $temp;
                    $temp = array();
                }
            }
            $temp[] = null;
            $ret[] = $temp;
            return $ret;
        } else {
            return [null, null, null, null];
        }
    }

    /**
     * Parses a custom CakePHP query for conditions search of the images database
     * @param $array
     * @param bool $itemsAreArrays
     * @return array
     */
    private function queryTextValueParser($array, $itemsAreArrays = false)
    {
        if (!is_array($array)) {
            return $array;
        }

        // if items are arrays, parse them differently
        if ($itemsAreArrays) {
            $ret = array();
            foreach ($array as $item) {
                $ret[] = $this->queryTextValueParser($item, false);
            }
            return $ret;
        }

        // check if its not string, then parse it as arrays
        if (is_array($array[0])) {
            return $this->queryTextValueParser($array, true);
        }

        if (count($array) > 2) {
            $op = $this->queryOperator($array[1]);
            if ($op == 'OR' || $op == 'AND') {
                throw new Exception('Operator AND or OR need to be after expression');
            }
            if ($array[0] !== 'filename' && $array[0] !== 'imgw' && $array[0] !== 'imgh') {
                throw new Exception('Only filename, imgw and imgh are allowed');
            }

            if (count($array) == 5) {
                if ($array[3] == 'OR') {
                    return [
                        (string)$array[0] . ' ' . $op => ($op !== 'LIKE') ? (int)$array[2] : '%' . $array[2] . '%',
                        (string)$array[3] => $this->queryTextValueParser($array[4])
                    ];
                } else {
                    return [
                        (string)$array[0] . ' ' . $op => ($op !== 'LIKE') ? (int)$array[2] : '%' . $array[2] . '%',
                        $this->queryTextValueParser($array[4])
                    ];
                }

            } else if (count($array) == 4) {
                if (null !== $array[3]) {
                    if ($array[3] == 'OR') {
                        return [
                            (string)$array[3] => [
                                (string)$array[0] . ' ' . $op => ($op !== 'LIKE') ? (int)$array[2] : '%' . $array[2] . '%'
                            ]
                        ];
                    } else {
                        return [
                            (string)$array[0] . ' ' . $op => ($op !== 'LIKE') ? (int)$array[2] : '%' . $array[2] . '%'
                        ];
                    }

                } else {
                    // probably its null, so return just one text value
                    return [
                        (string)$array[0] . ' ' . $op => ($op !== 'LIKE') ? (int)$array[2] : '%' . $array[2] . '%'
                    ];
                }
            } else {
                return [
                    (string)$array[0] . ' ' . $op => ($op !== 'LIKE') ? (int)$array[2] : '%' . $array[2] . '%'
                ];
            }
        } else {
            throw new Exception('Too little parameters for the query');
        }

    }

    /**
     * Checks the query operator, it throws error if not valid one
     * @param $op
     * @return string
     */
    private function queryOperator($op)
    {
        switch ($op = trim($op)) {
            case 'CONTAINS':
                return 'LIKE';
                break;
            case '<':
            case '>':
            case '=':
            case 'OR':
            case 'AND':
                return $op;
                break;
            default:
                throw new Exception('Wrong or missing operator, allowed only CONTAINS, <, >, =, OR, AND');
                break;
        }
    }

    /**
     * Rearanges previously parsed query for conditions, and fixes OR clauses
     * @param $query
     * @param null $parent
     * @param null $index
     * @return array
     */
    private function queryRearange($query, &$parent = null, $index = null)
    {
        if (!is_array($query)) {
            return $query;
        }

        $ret = array();
        $count = count($query);
        $i = 0;
        foreach ($query as $q => &$v) {
            if (is_array($v)) {
                if ($q === 'OR') {
                    $next = $parent[$index + 1];
                    if ($next !== null) {
                        $key = key($next);
                        $ret[$q] = [
                            $this->queryRearange($v),
                            $key => $parent[$i + 1][$key]
                        ];
                        unset($parent[$i + 1]);
                    } else {
                        $ret[$q] = $this->queryRearange($v);
                    }
                } else {
                    $ret[$q] = $this->queryRearange($v, $query, $i);
                }

            } else {
                $ret[$q] = $v;
            }
            $i++;
        }

        return $ret;
    }

    /**
     * View method
     *
     * @param string|null $id Image id.
     * @return \Cake\Http\Response|void
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function view($id = null)
    {
        $image = $this->Images->get($id, [
            'contain' => []
        ]);

        $this->set('image', $image);
        $this->set('_serialize', ['image']);
    }

    /**
     * Add method
     *
     * @return \Cake\Http\Response|null Redirects on successful add, renders view otherwise.
     */
    public function add()
    {
        $image = $this->Images->newEntity();
        if ($this->request->is('post')) {
            $image = $this->Images->patchEntity($image, $this->request->getData());

            if ($this->Images->save($image)) {
                $ip = new ImageProcessor();

                $imagePath = WWW_ROOT . 'img' . DS . 'images' . DS . 'filename' . DS . $image->dir . DS;
                $imageP = $ip->open($imagePath . $image->filename);
                $top = $this->request->getData('top');
                $left = $this->request->getData('left');
                $width = $this->request->getData('imgw');
                $height = $this->request->getData('imgh');
                $imageCropped = $imageP->crop([
                    'cropX' => is_numeric($left) ? $left : 0,
                    'cropY' => is_numeric($top) ? $top : 0,
                    'width' => is_numeric($width) ? $width : $imageP->getImageSize()[0],
                    'height' => is_numeric($height) ? $height : $imageP->getImageSize()[1]
                ]);

                if ($imageCropped->save($imagePath . 'cropped_' . $image->filename, ['format' => 'jpg'])) {
                    $this->Flash->success(__('The image has been saved.'));

                    return $this->redirect(['action' => 'index']);
                }
            }
            $this->Flash->error(__('The image could not be saved. Please, try again.'));
        }
        $this->set(compact('image'));
        $this->set('_serialize', ['image']);
    }

    /**
     * Edit method
     *
     * @param string|null $id Image id.
     * @return \Cake\Http\Response|null Redirects on successful edit, renders view otherwise.
     * @throws \Cake\Network\Exception\NotFoundException When record not found.
     */
    public function edit($id = null)
    {
        $image = $this->Images->get($id, [
            'contain' => []
        ]);
        if ($this->request->is(['patch', 'post', 'put'])) {
            $image = $this->Images->patchEntity($image, $this->request->getData());
            if ($this->Images->save($image)) {
                $this->Flash->success(__('The image has been saved.'));

                return $this->redirect(['action' => 'index']);
            }
            $this->Flash->error(__('The image could not be saved. Please, try again.'));
        }
        $this->set(compact('image'));
        $this->set('_serialize', ['image']);
    }

    /**
     * Delete method
     *
     * @param string|null $id Image id.
     * @return \Cake\Http\Response|null Redirects to index.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function delete($id = null)
    {
        $this->request->allowMethod(['post', 'delete']);
        $image = $this->Images->get($id);
        if ($this->Images->delete($image)) {
            $this->Flash->success(__('The image has been deleted.'));
        } else {
            $this->Flash->error(__('The image could not be deleted. Please, try again.'));
        }

        return $this->redirect(['action' => 'index']);
    }
}
