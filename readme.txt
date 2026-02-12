Nathan Kitchens Battleship V2

This is a browser based Battleship game with JavaScript as the front-end and PHP backend.

My first iteratition was the player picking the ship placement and validation. Players can rotate the ships and place them on the board and the layout is sent to the server for validation.

The second iteration is persistent storage with SQLite this was so that the game would save the game state if it is refreshed. 

Some limitations are that if all ships are not placed and the browser is reset it will restart the entire placement phase. The AI opponent is very simple and does not use much startegy when playing the game.

To run this game you need to place the BattleshipV2 folder in your XAMPP using the path /Applications/XAMPP/xamppfiles/htdocs/BattleshipV2/

Then run XAMPP and access the game using the URL http://localhost/BattleshipV2/


Here is the recording link
https://www.loom.com/share/8c5bd8edf20f4427a0ee72a2170d4571
