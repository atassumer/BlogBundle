<?php

namespace Desarrolla2\Bundle\BlogBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Desarrolla2\Bundle\BlogBundle\Entity\Post;
use Desarrolla2\Bundle\BlogBundle\Entity\Comment;
use Desarrolla2\DB\DB;
use Desarrolla2\DB\Adapter\MySQL;

class ImportFromWordPressCommand extends ContainerAwareCommand {

    protected $output;
    protected $input;
    protected $db;
    protected $source;

    /**
     * @access protected
     * @return void
     */
    protected function configure() {
        $this
                ->setName('blog:import:from-wordpress')
                ->setDescription('Import Post and Comments from wordpress')
                ->addArgument('host', InputArgument::REQUIRED, 'database host')
                ->addArgument('user', InputArgument::REQUIRED, 'database user')
                ->addArgument('pass', InputArgument::REQUIRED, 'database pass')
                ->addArgument('database', InputArgument::REQUIRED, 'database name')
        ;
    }

    /**
     * 
     * @param \Symfony\Component\Console\Input\InputInterface $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     */
    protected function initialize(InputInterface $input, OutputInterface $output) {
        parent::initialize($input, $output);
        $this->input = $input;
        $this->output = $output;
        $this->em = $this->getContainer()->get('doctrine')->getEntityManager();
        $this->db = new DB;
        $this->db->setAdaper(new MySQL);
        $this->db->setOptions(array(
            'database' => $input->getArgument('database'),
            'username' => $input->getArgument('user'),
            'hostname' => $input->getArgument('host'),
            'password' => $input->getArgument('pass'),
        ));

        $this->db->connect();
    }

    /**
     * @param InputInterface  $input  Inpunt arguments
     * @param OutputInterface $output Output stream
     */
    protected function execute(InputInterface $input, OutputInterface $output) {
        $n_post = 0;
        $n_comment = 0;

        $sql = ' SELECT ID AS id, ' .
                ' post_title AS post_title, ' .
                ' post_content AS post_content, ' .
                ' post_excerpt AS post_excerpt, ' .
                ' post_date AS post_date , ' .
                ' post_modified AS post_modified, ' .
                ' post_status AS status, ' .
                ' FROM wp_posts ' .
                ' WHERE post_parent = 0 ' .
                ' AND ( post_type = \'page\' OR post_type = \'post\' ) ' .
                ' AND ( post_status = \'publish\' ) ';

        $posts = $this->db->fetch_objects($sql);

        foreach ($posts as $p) {
            $output->writeln($p->id . ' <info>' . $p->post_title . '</info>');

            $post = new Post();
            $post->setName($p->post_title);
            $post->setContent($p->post_content);
            $post->setIntro($p->post_excerpt);
            $post->setCreatedAt(new \DateTime($p->post_date));
            $post->setUpdatedAt(new \DateTime($p->post_modified));
            $post->setPublishedAt(new \DateTime($p->post_date));
            $post->setIsPublished(true);

            $sql = ' SELECT comment_author AS comment_author, ' .
                    ' comment_author_email AS comment_author_email,  ' .
                    ' comment_date AS comment_date,' .
                    ' comment_content AS comment_content, ' .
                    ' comment_author_url AS comment_author_url ' .
                    ' FROM wp_comments ' .
                    ' WHERE comment_post_ID = ' . $p->id .
                    ' AND comment_approved  = 1 ';

            $comments = $this->db->fetch_objects($sql);
            foreach ($comments as $c) {
                $output->writeln($p->id . ' <comment>' . $c->comment_content . '</comment>');
                $comment = new Comment();
                $comment->setUserName($c->comment_author);
                $comment->setUserEmail($c->comment_author_email);
                $comment->setUserWeb($c->comment_author_url);
                $comment->setContent($c->comment_content);
                $comment->setCreatedAt(new \DateTime($c->comment_date));
                $comment->setStatus(1);
                $comment->setPost($post);
                $this->em->persist($comment);
                $n_comment++;
            }
            $this->em->persist($post);
            $n_post++;
            $this->em->flush();
        }

        $output->writeln('Post <info>' . $n_post . '</info>');
        $output->writeln('Comments <info>' . $n_comment . '</info>');
    }

    /**
     * 
     * @param type $sql
     * @return type
     */
    protected function query($sql) {
        $this->output->writeln('SQL <comment>' . $sql . '</comment>');
        return mysql_query($sql);
    }

}
