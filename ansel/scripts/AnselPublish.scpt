FasdUAS 1.101.10   ��   ��    k             l     ��  ��    T N An Applescript script to publish a folder of photos to a remote Ansel server.     � 	 	 �   A n   A p p l e s c r i p t   s c r i p t   t o   p u b l i s h   a   f o l d e r   o f   p h o t o s   t o   a   r e m o t e   A n s e l   s e r v e r .   
  
 l     ��  ��    ; 5 Just drop the folder on the script, and away you go.     �   j   J u s t   d r o p   t h e   f o l d e r   o n   t h e   s c r i p t ,   a n d   a w a y   y o u   g o .      l     ��  ��    W Q Edit the settings below as needed in Script Editor; then Save As... Application.     �   �   E d i t   t h e   s e t t i n g s   b e l o w   a s   n e e d e d   i n   S c r i p t   E d i t o r ;   t h e n   S a v e   A s . . .   A p p l i c a t i o n .      l     ��������  ��  ��        l     ��  ��    ' ! Path to remote_import.php script     �   B   P a t h   t o   r e m o t e _ i m p o r t . p h p   s c r i p t      j     �� �� 0 commandline commandLine  m        �   r / o p t / l o c a l / b i n / p h p   / h o r d e / a n s e l / s c r i p t s / r e m o t e _ i m p o r t . p h p     !   l     ��������  ��  ��   !  " # " l     �� $ %��   $ %  URL to rpc.php on horde server    % � & & >   U R L   t o   r p c . p h p   o n   h o r d e   s e r v e r #  ' ( ' j    �� )�� 0 
remotehost   ) m     * * � + + @ h t t p : / / e x a m p l e . c o m / h o r d e / r p c . p h p (  , - , l     ��������  ��  ��   -  . / . l     �� 0 1��   0   Credentials    1 � 2 2    C r e d e n t i a l s /  3 4 3 j    �� 5�� 0 username   5 m     6 6 � 7 7   4  8 9 8 j   	 �� :�� 0 pass   : m   	 
 ; ; � < <   9  = > = l     ��������  ��  ��   >  ? @ ? i     A B A I     �� C��
�� .aevtodocnull  �    alis C o      ���� 0 folder_name  ��   B k     B D D  E F E r      G H G n      I J I 1    ��
�� 
strq J n      K L K 1    ��
�� 
psxp L o     ���� 0 folder_name   H o      ���� 0 
folderpath 
folderPath F  M N M l   ��������  ��  ��   N  O P O l   �� Q R��   Q   Put it together    R � S S     P u t   i t   t o g e t h e r P  T U T r    / V W V b    ) X Y X b    # Z [ Z b    ! \ ] \ b     ^ _ ^ b     ` a ` b     b c b b     d e d b     f g f o    ���� 0 commandline commandLine g m     h h � i i    - - u s e r n a m e   e o    ���� 0 username   c m     j j � k k    - - p a s s w o r d   a o    ���� 0 pass   _ m     l l � m m    - - d i r   ] o     ���� 0 
folderpath 
folderPath [ m   ! " n n � o o    - - r e m o t e h o s t   Y o   # (���� 0 
remotehost   W o      ���� 0 commandline commandLine U  p q p l  0 0��������  ��  ��   q  r s r O   0 @ t u t I  4 ?�� v��
�� .coredoscnull��� ��� ctxt v b   4 ; w x w o   4 9���� 0 commandline commandLine x m   9 : y y � z z  ;   e x i t ;��   u m   0 1 { {�                                                                                      @  alis    R  Maya                       ��uH+   �;ETerminal.app                                                    ��8        ����  	                	Utilities     ��R      �Vx     �;E �;D  (Maya:Applications:Utilities:Terminal.app    T e r m i n a l . a p p  
  M a y a  #Applications/Utilities/Terminal.app   / ��   s  |�� | l  A A��������  ��  ��  ��   @  } ~ } l     ��������  ��  ��   ~  ��  l     ��������  ��  ��  ��       �� �  * 6 ; ���   � ������������ 0 commandline commandLine�� 0 
remotehost  �� 0 username  �� 0 pass  
�� .aevtodocnull  �    alis � �� B���� � ���
�� .aevtodocnull  �    alis�� 0 folder_name  ��   � ������ 0 folder_name  �� 0 
folderpath 
folderPath � 	���� h j l n { y��
�� 
psxp
�� 
strq
�� .coredoscnull��� ��� ctxt�� C��,�,E�Ob   �%b  %�%b  %�%�%�%b  %Ec   O� b   �%j UOPascr  ��ޭ