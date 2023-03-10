describe('client-ui: ColorModeSwitch component', () => {
  beforeEach(() => cy.visit('/iframe.html?id=colormodeswitch--primary'));

  it('should render the component', () => {
    cy.get('h1').should('contain', 'Welcome to ColorModeSwitch!');
  });
});
